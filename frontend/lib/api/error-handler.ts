import axios from 'axios';

export class APIError extends Error {
  constructor(
    public code: number,
    public message: string,
    public data?: any
  ) {
    super(message);
    this.name = 'APIError';
  }

  static from(error: any): APIError {
    if (error instanceof APIError) {
      return error;
    }

    if (axios.isAxiosError(error)) {
      const code = error.response?.status || 500;
      const message = error.response?.data?.error || error.message;
      const data = error.response?.data;

      return new APIError(code, message, data);
    }

    return new APIError(500, error?.message || 'Unknown error occurred');
  }

  isUnauthorized(): boolean {
    return this.code === 401;
  }

  isForbidden(): boolean {
    return this.code === 403;
  }

  isNotFound(): boolean {
    return this.code === 404;
  }

  isValidationError(): boolean {
    return this.code === 422 || this.code === 400;
  }

  getValidationErrors(): Record<string, string[]> {
    if (this.data?.errors) {
      return this.data.errors;
    }
    return {};
  }
}

export class APIErrorHandler {
  static handle(error: any): never {
    const apiError = APIError.from(error);
    this.log(apiError);
    throw apiError;
  }

  static async retry<T>(
    fn: () => Promise<T>,
    maxRetries: number = 3,
    delayMs: number = 1000
  ): Promise<T> {
    let lastError: Error | null = null;

    for (let i = 0; i < maxRetries; i++) {
      try {
        return await fn();
      } catch (error) {
        lastError = APIError.from(error);

        // Don't retry for client errors (4xx)
        if (lastError.code >= 400 && lastError.code < 500) {
          throw lastError;
        }

        // Wait before retrying
        if (i < maxRetries - 1) {
          await new Promise((resolve) => setTimeout(resolve, delayMs * Math.pow(2, i)));
        }
      }
    }

    throw lastError || new APIError(500, 'Max retries exceeded');
  }

  private static log(error: APIError) {
    const logEntry = {
      timestamp: new Date().toISOString(),
      code: error.code,
      message: error.message,
      data: error.data,
    };

    console.error('[API Error]', logEntry);

    // Send to error tracking service (Sentry, etc.)
    if (typeof window !== 'undefined' && window.__errorTracking) {
      window.__errorTracking?.captureException(error, {
        tags: { 'error.type': 'api' },
        extra: logEntry,
      });
    }
  }

  static getErrorMessage(error: any): string {
    if (error instanceof APIError) {
      switch (error.code) {
        case 400:
          return 'Invalid request. Please check your input.';
        case 401:
          return 'Your session has expired. Please log in again.';
        case 403:
          return 'You do not have permission to perform this action.';
        case 404:
          return 'The requested resource was not found.';
        case 422:
          return 'Validation failed. Please check the highlighted fields.';
        case 429:
          return 'Too many requests. Please try again later.';
        case 500:
        case 502:
        case 503:
          return 'Server error. Please try again later.';
        default:
          return error.message || 'An error occurred';
      }
    }

    return 'An unexpected error occurred';
  }
}

export function useAPIErrorHandler() {
  return {
    handle: APIErrorHandler.handle.bind(APIErrorHandler),
    retry: APIErrorHandler.retry.bind(APIErrorHandler),
    getErrorMessage: APIErrorHandler.getErrorMessage.bind(APIErrorHandler),
  };
}
