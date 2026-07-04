import { useEffect, useRef } from 'react';
import { useCallback } from 'react';

// Custom hook for real-time lead updates
export function useRealtimeLeads(onLeadUpdate?: (lead: any) => void) {
  const subscriptionRef = useRef<any>(null);
  const isConnectedRef = useRef(false);

  useEffect(() => {
    // This hook prepares the structure for Supabase real-time
    // In production, connect to Supabase real-time subscriptions:
    
    // Example implementation:
    // const subscription = supabase
    //   .from('leads')
    //   .on('*', (payload) => {
    //     onLeadUpdate?.(payload.new);
    //   })
    //   .subscribe();

    // For now, simulate with polling (development mode)
    const pollInterval = setInterval(() => {
      // Simulated real-time updates
      if (isConnectedRef.current && onLeadUpdate) {
        // Updates would come from WebSocket in production
      }
    }, 5000);

    isConnectedRef.current = true;
    subscriptionRef.current = pollInterval;

    return () => {
      isConnectedRef.current = false;
      if (subscriptionRef.current) {
        clearInterval(subscriptionRef.current);
      }
    };
  }, [onLeadUpdate]);

  return {
    connected: isConnectedRef.current,
    disconnect: () => {
      isConnectedRef.current = false;
    },
  };
}

// Hook for real-time conversation messages
export function useRealtimeMessages(conversationId: string, onMessage?: (message: any) => void) {
  const subscriptionRef = useRef<any>(null);

  useEffect(() => {
    if (!conversationId) return;

    // In production: Supabase real-time subscription
    // const subscription = supabase
    //   .from(`messages:conversation_id=eq.${conversationId}`)
    //   .on('INSERT', (payload) => {
    //     onMessage?.(payload.new);
    //   })
    //   .subscribe();

    return () => {
      // Cleanup subscription
      if (subscriptionRef.current) {
        subscriptionRef.current.unsubscribe?.();
      }
    };
  }, [conversationId, onMessage]);

  return { connected: true };
}

// Hook for AI streaming responses
export function useAIStream() {
  const abortControllerRef = useRef<AbortController | null>(null);

  const streamChat = useCallback(async function* (messages: any[]) {
    abortControllerRef.current = new AbortController();

    try {
      // Call backend streaming endpoint
      const response = await fetch('/api/v1/ai/stream', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('eckox_auth_token')}`,
        },
        body: JSON.stringify({ messages }),
        signal: abortControllerRef.current.signal,
      });

      if (!response.ok) {
        throw new Error('Stream failed');
      }

      const reader = response.body?.getReader();
      if (!reader) return;

      const decoder = new TextDecoder();
      let buffer = '';

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() || '';

        for (const line of lines) {
          if (line.startsWith('data: ')) {
            yield line.slice(6);
          }
        }
      }
    } catch (error) {
      if (error instanceof DOMException && error.name === 'AbortError') {
        console.log('Stream cancelled');
      } else {
        throw error;
      }
    }
  }, []);

  const cancelStream = useCallback(() => {
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }
  }, []);

  return { streamChat, cancelStream };
}

// Hook for notifications
export function useNotifications(userId: string) {
  const subscriptionRef = useRef<any>(null);

  useEffect(() => {
    if (!userId) return;

    // In production: Subscribe to notifications via Supabase
    // const subscription = supabase
    //   .from(`notifications:user_id=eq.${userId}`)
    //   .on('INSERT', (payload) => {
    //     // Show notification toast
    //     notificationService.show(payload.new);
    //   })
    //   .subscribe();

    return () => {
      if (subscriptionRef.current) {
        subscriptionRef.current.unsubscribe?.();
      }
    };
  }, [userId]);

  return { connected: true };
}
