import axios from 'axios';
import { router } from '@inertiajs/vue3';
import {
  getBacklogApiKey,
  syncBacklogApiKeyCookie,
} from './composables/useBacklogApiKey';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

syncBacklogApiKeyCookie();

axios.interceptors.request.use((config) => {
  const apiKey = getBacklogApiKey();

  if (apiKey) {
    config.headers['X-Backlog-Api-Key'] = apiKey;
  }

  return config;
});

router.on('before', (event) => {
  const apiKey = getBacklogApiKey();

  if (!apiKey) {
    return;
  }

  event.detail.visit.headers = {
    ...event.detail.visit.headers,
    'X-Backlog-Api-Key': apiKey,
  };
});
