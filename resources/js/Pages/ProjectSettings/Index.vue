<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useBacklogProjectSettings } from '@/composables/useBacklogProjectSettings';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

defineProps({
  has_api_key: {
    type: Boolean,
    required: true,
  },
});

const {
  isProjectSelected,
  toggleProject,
  selectAll,
  clearAll,
  initializeDefaults,
  selectedIds,
} = useBacklogProjectSettings();

const projects = ref([]);
const currentUser = ref(null);
const loading = ref(false);
const refreshing = ref(false);
const fetchedAt = ref(null);
const loadError = ref(null);
const expandedProjectId = ref(null);

const activeProjects = computed(() => projects.value.filter((project) => !project.archived));
const selectedCount = computed(() => selectedIds.value.length);

const formatFetchedAt = (isoString) => {
  if (!isoString) {
    return '—';
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(isoString));
};

const loadCurrentUser = async () => {
  const response = await window.axios.get(route('project-settings.myself'));
  currentUser.value = response?.data?.user ?? null;
};

const loadProjects = async (forceRefresh = false) => {
  loadError.value = null;
  loading.value = !forceRefresh;
  refreshing.value = forceRefresh;

  try {
    const response = forceRefresh
      ? await window.axios.post(route('project-settings.refresh'))
      : await window.axios.get(route('project-settings.projects'));

    projects.value = Array.isArray(response?.data?.projects) ? response.data.projects : [];
    fetchedAt.value = response?.data?.fetched_at ?? null;
    initializeDefaults(projects.value);
  } catch (error) {
    loadError.value =
      error?.response?.data?.message ?? 'Failed to load Backlog projects.';
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
};

const handleSelectAll = () => {
  selectAll(activeProjects.value.map((project) => project.id));
};

const toggleExpanded = (projectId) => {
  expandedProjectId.value = expandedProjectId.value === projectId ? null : projectId;
};

const fieldRoleLabel = (role) => {
  if (role === 'person_in_charge') {
    return 'Person in charge';
  }

  if (role === 'sub_person_in_charge') {
    return 'Sub person in charge';
  }

  return 'Unassigned';
};

onMounted(async () => {
  await Promise.all([loadCurrentUser(), loadProjects()]);
});
</script>

<template>
  <Head title="Project Settings" />

  <PublicLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">Project Settings</h2>
    </template>

    <div class="py-8">
      <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div
          v-if="!has_api_key"
          class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center shadow-sm"
        >
          <p class="text-lg font-medium text-gray-900">
            Enter your Backlog API key to get started
          </p>
        </div>

        <template v-else>
          <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <p class="text-sm text-gray-500">
                  Selected projects are used for all Backlog API calls in this app.
                </p>
                <p v-if="currentUser" class="mt-1 text-sm text-gray-700">
                  Logged in as
                  <span class="font-medium">{{ currentUser.name }}</span>
                  (ID: {{ currentUser.id }})
                </p>
                <p class="mt-1 text-xs text-gray-400">
                  {{ selectedCount.toLocaleString() }} of
                  {{ activeProjects.length.toLocaleString() }} active projects selected
                  · Last fetched: {{ formatFetchedAt(fetchedAt) }}
                </p>
              </div>

              <div class="flex flex-wrap gap-2">
                <SecondaryButton type="button" @click="handleSelectAll">Select all</SecondaryButton>
                <SecondaryButton type="button" @click="clearAll">Clear all</SecondaryButton>
                <PrimaryButton type="button" :disabled="refreshing" @click="loadProjects(true)">
                  <span v-if="refreshing">Refreshing…</span>
                  <span v-else>Refresh projects</span>
                </PrimaryButton>
              </div>
            </div>
          </div>

          <div
            v-if="loadError"
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
          >
            {{ loadError }}
          </div>

          <div
            v-if="loading"
            class="rounded-lg border border-gray-200 bg-white px-6 py-16 text-center text-sm text-gray-500 shadow-sm"
          >
            Loading projects, members, and custom fields…
          </div>

          <div v-else class="space-y-3">
            <article
              v-for="project in projects"
              :key="project.id"
              class="rounded-lg border bg-white shadow-sm"
              :class="isProjectSelected(project.id) ? 'border-indigo-300' : 'border-gray-200'"
            >
              <div class="flex items-start gap-4 p-4">
                <input
                  :id="`project-${project.id}`"
                  type="checkbox"
                  class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  :checked="isProjectSelected(project.id)"
                  :disabled="project.archived"
                  @change="toggleProject(project.id)"
                />

                <div class="min-w-0 flex-1">
                  <label :for="`project-${project.id}`" class="cursor-pointer">
                    <div class="flex flex-wrap items-center gap-2">
                      <h3 class="text-base font-semibold text-gray-900">{{ project.name }}</h3>
                      <span class="text-sm text-indigo-600">{{ project.project_key }}</span>
                      <span
                        v-if="project.archived"
                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                      >
                        Archived
                      </span>
                    </div>
                  </label>

                  <p class="mt-1 text-sm text-gray-500">
                    {{ project.member_count.toLocaleString() }} members
                    <span v-if="project.person_in_charge_field">
                      · Person in charge:
                      <span class="font-medium text-gray-700">
                        {{ project.person_in_charge_field.name }}
                        (ID {{ project.person_in_charge_field.id }})
                      </span>
                    </span>
                    <span v-else>· No person-in-charge custom field detected for this project</span>
                  </p>

                  <p
                    v-if="project.sub_person_in_charge_fields?.length"
                    class="mt-1 text-sm text-gray-500"
                  >
                    Sub person in charge:
                    <span
                      v-for="field in project.sub_person_in_charge_fields"
                      :key="field.id"
                      class="mr-2 font-medium text-gray-700"
                    >
                      {{ field.name }} (ID {{ field.id }})
                    </span>
                  </p>
                </div>

                <button
                  type="button"
                  class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                  @click="toggleExpanded(project.id)"
                >
                  {{ expandedProjectId === project.id ? 'Hide details' : 'Show details' }}
                </button>
              </div>

              <div
                v-if="expandedProjectId === project.id"
                class="border-t border-gray-100 bg-gray-50 px-4 py-4"
              >
                <div class="grid gap-6 lg:grid-cols-2">
                  <div>
                    <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                      Members
                    </h4>
                    <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto text-sm text-gray-700">
                      <li v-for="member in project.members" :key="member.id">
                        {{ member.name }}
                        <span class="text-gray-400">({{ member.user_id }} · ID {{ member.id }})</span>
                      </li>
                    </ul>
                  </div>

                  <div>
                    <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                      Custom fields
                    </h4>
                    <ul class="mt-2 space-y-2 text-sm">
                      <li
                        v-for="field in project.custom_fields"
                        :key="field.id"
                        class="rounded-md border border-gray-200 bg-white p-3"
                      >
                        <p class="font-medium text-gray-900">
                          {{ field.name }}
                          <span class="text-gray-400">· ID {{ field.id }}</span>
                        </p>
                        <p class="text-xs text-gray-500">
                          Type: {{ field.type_name }} · API:
                          <code class="text-indigo-600">{{ field.api_filter }}</code>
                        </p>
                        <p class="text-xs text-gray-500">
                          UI filter example:
                          <code>{{ field.ui_filter_example }}</code>
                        </p>
                        <p v-if="field.role" class="mt-1 text-xs font-medium text-green-700">
                          Detected as: {{ fieldRoleLabel(field.role) }}
                        </p>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </article>
          </div>
        </template>
      </div>
    </div>
  </PublicLayout>
</template>
