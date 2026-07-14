<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import BacklogApiKeyInput from '@/Components/BacklogApiKeyInput.vue';
import { useBacklogProjectSettings } from '@/composables/useBacklogProjectSettings';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
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
const search = ref('');
const showArchived = ref(false);

const activeProjects = computed(() => projects.value.filter((project) => !project.archived));
const archivedProjects = computed(() => projects.value.filter((project) => project.archived));
const selectedCount = computed(() => selectedIds.value.length);

const visibleProjects = computed(() => {
  const query = search.value.trim().toLowerCase();
  const pool = showArchived.value
    ? projects.value
    : activeProjects.value;

  if (!query) {
    return pool;
  }

  return pool.filter((project) => {
    const haystack = [project.name, project.project_key]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return haystack.includes(query);
  });
});

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

  if (role === 'qa_in_charge') {
    return 'QA in charge';
  }

  if (role === 'sub_qa_in_charge') {
    return 'Sub QA in charge';
  }

  return 'Unassigned';
};

const roleSummary = (project) => {
  const parts = [];

  if (project.person_in_charge_field) {
    parts.push(`PIC: ${project.person_in_charge_field.name}`);
  }

  if (project.qa_in_charge_field) {
    parts.push(`QA: ${project.qa_in_charge_field.name}`);
  }

  if (parts.length === 0) {
    return 'No PIC/QA fields detected';
  }

  return parts.join(' · ');
};

onMounted(async () => {
  await Promise.all([loadCurrentUser(), loadProjects()]);
});
</script>

<template>
  <Head title="Project Settings" />

  <PublicLayout>
    <template #header>
      <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Settings</p>
          <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Projects</h2>
        </div>
        <Link
          :href="route('notifications.index')"
          class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
        >
          ← Back to app
        </Link>
      </div>
    </template>

    <div class="py-5">
      <div class="mx-auto max-w-4xl space-y-3 px-4 sm:px-6 lg:px-8">
        <div
          class="rounded-lg border border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none dark:ring-1 dark:ring-white/10 sm:px-5"
        >
          <p class="mb-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
            Backlog API key
          </p>
          <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
            Stored in this browser and sent with Backlog API requests.
          </p>
          <BacklogApiKeyInput
            stacked
            input-id="backlog-api-key-settings"
          />
        </div>

        <div
          v-if="!has_api_key"
          class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:shadow-none dark:ring-1 dark:ring-white/10"
        >
          <p class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Enter your Backlog API key above to load projects
          </p>
        </div>

        <template v-else>
          <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none dark:ring-1 dark:ring-white/10">
            <div class="space-y-2 border-b border-gray-200 px-3 py-3 dark:border-gray-700 sm:px-4">
              <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                  <span class="font-semibold text-gray-900 dark:text-gray-100">{{ selectedCount.toLocaleString() }}</span>
                  of
                  <span class="font-semibold text-gray-900 dark:text-gray-100">{{ activeProjects.length.toLocaleString() }}</span>
                  active projects selected
                </div>

                <div class="flex w-full items-center gap-2 sm:w-auto">
                  <SecondaryButton
                    type="button"
                    class="flex-1 justify-center sm:flex-none"
                    @click="handleSelectAll"
                  >
                    Select all
                  </SecondaryButton>
                  <SecondaryButton
                    type="button"
                    class="flex-1 justify-center sm:flex-none"
                    @click="clearAll"
                  >
                    Clear
                  </SecondaryButton>
                  <PrimaryButton
                    type="button"
                    class="flex-1 justify-center sm:flex-none"
                    :disabled="refreshing"
                    @click="loadProjects(true)"
                  >
                    <span v-if="refreshing">Refetching…</span>
                    <span v-else>Refetch</span>
                  </PrimaryButton>
                </div>
              </div>

              <p class="text-xs text-gray-400 dark:text-gray-500">
                <span v-if="currentUser">Logged in as {{ currentUser.name }}</span>
                <span v-if="currentUser && fetchedAt"> · </span>
                <span v-if="fetchedAt">Fetched {{ formatFetchedAt(fetchedAt) }}</span>
              </p>

              <p class="text-xs text-gray-500 dark:text-gray-400">
                Enabled projects are sent with every Backlog API request from this browser.
              </p>
            </div>

            <div class="border-b border-gray-100 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-900/50 sm:px-4">
              <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <TextInput
                  v-model="search"
                  type="search"
                  class="block w-full py-1.5 text-sm shadow-sm"
                  placeholder="Search projects…"
                />
                <label class="flex shrink-0 items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                  <input
                    v-model="showArchived"
                    type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600"
                  />
                  Show archived
                </label>
              </div>
            </div>

            <div
              v-if="loadError"
              class="border-b border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300 sm:px-4"
            >
              {{ loadError }}
            </div>

            <div
              v-if="loading"
              class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400"
            >
              Loading projects…
            </div>

            <ul v-else-if="visibleProjects.length === 0" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
              <p class="font-medium text-gray-900 dark:text-gray-100">No projects match your filters</p>
            </ul>

            <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
              <li
                v-for="project in visibleProjects"
                :key="project.id"
                class="px-3 py-3 sm:px-4"
                :class="[
                  isProjectSelected(project.id) ? 'bg-indigo-50/40 dark:bg-indigo-950/40' : 'bg-white dark:bg-gray-800',
                  project.archived ? 'opacity-60' : '',
                ]"
              >
                <div class="flex items-start gap-3">
                  <input
                    :id="`project-${project.id}`"
                    type="checkbox"
                    class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600"
                    :checked="isProjectSelected(project.id)"
                    :disabled="project.archived"
                    @change="toggleProject(project.id)"
                  />

                  <div class="min-w-0 flex-1">
                    <label :for="`project-${project.id}`" class="block cursor-pointer">
                      <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ project.name }}</span>
                        <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400">{{ project.project_key }}</span>
                        <span
                          v-if="project.archived"
                          class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium uppercase text-gray-500 dark:bg-gray-700 dark:text-gray-400"
                        >
                          Archived
                        </span>
                      </div>

                      <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ project.member_count.toLocaleString() }} members
                        · {{ roleSummary(project) }}
                      </p>
                    </label>

                    <button
                      v-if="project.custom_fields?.length || project.members?.length"
                      type="button"
                      class="mt-1 text-[11px] font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                      @click="toggleExpanded(project.id)"
                    >
                      {{ expandedProjectId === project.id ? 'Hide details' : 'Details' }}
                    </button>

                    <div
                      v-if="expandedProjectId === project.id"
                      class="mt-2 space-y-3 rounded-md border border-gray-100 bg-gray-50 p-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400"
                    >
                      <div v-if="project.sub_person_in_charge_fields?.length">
                        <p class="font-medium text-gray-700 dark:text-gray-300">Sub PIC fields</p>
                        <p class="mt-0.5">
                          {{
                            project.sub_person_in_charge_fields
                              .map((field) => `${field.name} (${field.id})`)
                              .join(', ')
                          }}
                        </p>
                      </div>

                      <div v-if="project.sub_qa_in_charge_fields?.length">
                        <p class="font-medium text-gray-700 dark:text-gray-300">Sub QA fields</p>
                        <p class="mt-0.5">
                          {{
                            project.sub_qa_in_charge_fields
                              .map((field) => `${field.name} (${field.id})`)
                              .join(', ')
                          }}
                        </p>
                      </div>

                      <div v-if="project.members?.length" class="grid gap-3 sm:grid-cols-2">
                        <div>
                          <p class="font-medium text-gray-700 dark:text-gray-300">Members</p>
                          <ul class="mt-1 max-h-32 space-y-0.5 overflow-y-auto">
                            <li v-for="member in project.members" :key="member.id">
                              {{ member.name }}
                            </li>
                          </ul>
                        </div>

                        <div v-if="project.custom_fields?.length">
                          <p class="font-medium text-gray-700 dark:text-gray-300">Custom fields</p>
                          <ul class="mt-1 max-h-32 space-y-1 overflow-y-auto">
                            <li
                              v-for="field in project.custom_fields"
                              :key="field.id"
                              class="rounded border border-gray-200 bg-white px-2 py-1 dark:border-gray-700 dark:bg-gray-800"
                            >
                              <span class="font-medium text-gray-800 dark:text-gray-200">{{ field.name }}</span>
                              <span class="text-gray-400 dark:text-gray-500"> · {{ field.id }}</span>
                              <span
                                v-if="field.role"
                                class="ml-1 text-green-700 dark:text-green-300"
                              >
                                ({{ fieldRoleLabel(field.role) }})
                              </span>
                            </li>
                          </ul>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </li>
            </ul>

            <p
              v-if="!loading && archivedProjects.length > 0 && !showArchived"
              class="border-t border-gray-100 px-3 py-2 text-[11px] text-gray-400 dark:border-gray-700 dark:text-gray-500 sm:px-4"
            >
              {{ archivedProjects.length.toLocaleString() }} archived project(s) hidden.
              Enable “Show archived” to view them.
            </p>
          </div>
        </template>
      </div>
    </div>
  </PublicLayout>
</template>
