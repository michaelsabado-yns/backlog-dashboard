import axios from 'axios';
import { router } from '@inertiajs/vue3';
import {
  getBacklogApiKey,
  syncBacklogApiKeyCookie,
} from './composables/useBacklogApiKey';
import { getSelectedProjectIdsHeader } from './composables/useBacklogProjectSettings';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

syncBacklogApiKeyCookie();

const attachBacklogHeaders = (headers = {}) => {
  const nextHeaders = { ...headers };
  const apiKey = getBacklogApiKey();

  if (apiKey) {
    nextHeaders['X-Backlog-Api-Key'] = apiKey;
  }

  const projectIds = getSelectedProjectIdsHeader();

  if (projectIds !== null) {
    nextHeaders['X-Backlog-Project-Ids'] = projectIds;
  }

  return nextHeaders;
};

axios.interceptors.request.use((config) => {
  config.headers = attachBacklogHeaders(config.headers);

  return config;
});

router.on('before', (event) => {
  event.detail.visit.headers = attachBacklogHeaders(event.detail.visit.headers);
});
