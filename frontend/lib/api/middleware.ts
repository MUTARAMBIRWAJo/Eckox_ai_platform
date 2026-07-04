import { AxiosInstance } from 'axios';

// Request logging middleware
export function setupRequestLogging(client: AxiosInstance) {
  client.interceptors.request.use((config) => {
    if (process.env.NODE_ENV === 'development') {
      console.log('[API Request]', {
        method: config.method?.toUpperCase(),
        url: config.url,
        params: config.params,
        data: config.data ? JSON.stringify(config.data).slice(0, 200) : undefined,
      });
    }
    return config;
  });
}

// Response logging middleware
export function setupResponseLogging(client: AxiosInstance) {
  client.interceptors.response.use(
    (response) => {
      if (process.env.NODE_ENV === 'development') {
        console.log('[API Response]', {
          status: response.status,
          url: response.config.url,
          data: JSON.stringify(response.data).slice(0, 200),
        });
      }
      return response;
    },
    (error) => {
      if (process.env.NODE_ENV === 'development') {
        console.error('[API Error Response]', {
          status: error.response?.status,
          url: error.config?.url,
          error: error.response?.data?.error,
        });
      }
      return Promise.reject(error);
    }
  );
}

// Rate limiting client-side
export function setupRateLimiting(client: AxiosInstance) {
  const requestQueue: Array<{ config: any; resolve: any; reject: any }> = [];
  let processing = false;
  const requestsPerSecond = 10;
  const requestInterval = 1000 / requestsPerSecond;
  let lastRequestTime = 0;

  client.interceptors.request.use((config) => {
    return new Promise((resolve, reject) => {
      requestQueue.push({ config, resolve, reject });
      processQueue();
    });
  });

  function processQueue() {
    if (processing || requestQueue.length === 0) return;

    processing = true;
    const now = Date.now();
    const timeSinceLastRequest = now - lastRequestTime;

    if (timeSinceLastRequest >= requestInterval) {
      const { config, resolve } = requestQueue.shift()!;
      lastRequestTime = now;
      processing = false;
      resolve(config);
      processQueue();
    } else {
      setTimeout(() => {
        processing = false;
        processQueue();
      }, requestInterval - timeSinceLastRequest);
    }
  }
}

// Request deduplication
export function setupRequestDeduplication(client: AxiosInstance) {
  const pendingRequests = new Map<string, Promise<any>>();

  client.interceptors.request.use((config) => {
    // Create a unique key for the request
    const key = `${config.method}:${config.url}`;

    // Check if an identical request is already pending
    if (config.method?.toLowerCase() === 'get' && pendingRequests.has(key)) {
      // Return the existing promise instead of making a duplicate request
      return Promise.reject({
        isDuplicate: true,
        promise: pendingRequests.get(key),
      });
    }

    return config;
  });

  client.interceptors.response.use(
    (response) => {
      const key = `${response.config.method}:${response.config.url}`;
      pendingRequests.delete(key);
      return response;
    },
    (error) => {
      const key = `${error.config?.method}:${error.config?.url}`;
      pendingRequests.delete(key);
      return Promise.reject(error);
    }
  );
}

// Token refresh middleware
export function setupTokenRefresh(client: AxiosInstance, refreshTokenFn: () => Promise<string>) {
  let isRefreshing = false;
  let failedQueue: Array<{ resolve: any; reject: any }> = [];

  const processQueue = (error: any, token: string | null = null) => {
    failedQueue.forEach((prom) => {
      if (error) {
        prom.reject(error);
      } else {
        prom.resolve(token);
      }
    });

    failedQueue = [];
  };

  client.interceptors.response.use(
    (response) => response,
    async (error) => {
      const { config } = error;
      const originalRequest = config;

      if (error.response?.status === 401 && !originalRequest._retry) {
        if (isRefreshing) {
          return new Promise((resolve, reject) => {
            failedQueue.push({ resolve, reject });
          })
            .then((token) => {
              originalRequest.headers['Authorization'] = `Bearer ${token}`;
              return client(originalRequest);
            })
            .catch((err) => Promise.reject(err));
        }

        originalRequest._retry = true;
        isRefreshing = true;

        try {
          const newToken = await refreshTokenFn();
          client.defaults.headers.common['Authorization'] = `Bearer ${newToken}`;
          originalRequest.headers['Authorization'] = `Bearer ${newToken}`;
          processQueue(null, newToken);
          return client(originalRequest);
        } catch (err) {
          processQueue(err, null);
          // Redirect to login
          if (typeof window !== 'undefined') {
            window.location.href = '/login';
          }
          return Promise.reject(err);
        } finally {
          isRefreshing = false;
        }
      }

      return Promise.reject(error);
    }
  );
}

// Request timeout warning
export function setupTimeoutWarning(client: AxiosInstance) {
  const warningThreshold = 5000; // 5 seconds

  client.interceptors.request.use((config) => {
    const startTime = Date.now();

    // Store original response handler
    const originalResponseHandler = config.transformResponse;

    config.transformResponse = (data: any) => {
      const duration = Date.now() - startTime;

      if (duration > warningThreshold) {
        console.warn(`[Slow API] Request to ${config.url} took ${duration}ms`);
      }

      return originalResponseHandler
        ? (Array.isArray(originalResponseHandler)
            ? originalResponseHandler[0](data)
            : originalResponseHandler(data))
        : data;
    };

    return config;
  });
}

// Setup all middleware
export function setupAllMiddleware(client: AxiosInstance, refreshTokenFn: () => Promise<string>) {
  setupRequestLogging(client);
  setupResponseLogging(client);
  setupRateLimiting(client);
  setupRequestDeduplication(client);
  setupTokenRefresh(client, refreshTokenFn);
  setupTimeoutWarning(client);
}
