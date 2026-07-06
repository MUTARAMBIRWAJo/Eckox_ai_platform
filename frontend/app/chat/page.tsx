"use client";

import { useEffect, useRef, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { PageTransition } from "@/components/layout/page-transition";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card } from "@/components/ui/card";
import { ScrollArea } from "@/components/ui/scroll-area";
import { StreamingMessage } from "@/components/chat/streaming-message";
import { SuggestedPrompts } from "@/components/chat/suggested-prompts";
import { AIAPI } from "@/lib/api/ai.api";
import { useAuth } from "@/hooks/use-auth";
import { Send, StopCircle, Zap } from "lucide-react";

interface Message {
  id: string;
  role: "user" | "assistant";
  content: string;
  timestamp: Date;
  confidence?: number;
}

export default function ChatPage() {
  const { user, logout } = useAuth();
  const [messages, setMessages] = useState<Message[]>([
    {
      id: "1",
      role: "assistant",
      content: "Hello! I'm your AI sales assistant. I can help you with lead analysis, quote generation, market insights, and more. What would you like help with today?",
      timestamp: new Date(),
    },
  ]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [streamingMessageId, setStreamingMessageId] = useState<string | null>(null);
  const [abortController, setAbortController] = useState<AbortController | null>(null);
  const scrollRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    scrollRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  const handleSendMessage = async (text?: string) => {
    const messageText = text || input;
    if (!messageText.trim() || loading) return;

    const userMessage: Message = {
      id: Date.now().toString(),
      role: "user",
      content: messageText,
      timestamp: new Date(),
    };

    setMessages((prev) => [...prev, userMessage]);
    setInput("");
    setLoading(true);

    const assistantMessageId = (Date.now() + 1).toString();
    setStreamingMessageId(assistantMessageId);

    const controller = new AbortController();
    setAbortController(controller);

    try {
      const newMessage: Message = {
        id: assistantMessageId,
        role: "assistant",
        content: "",
        timestamp: new Date(),
      };

      setMessages((prev) => [...prev, newMessage]);

      const chatMessages = [
        ...messages.map((m) => ({ role: m.role, content: m.content })),
        { role: userMessage.role, content: userMessage.content },
      ];

      const stream = AIAPI.streamChat(chatMessages, controller.signal);
      let assistantContent = "";

      for await (const chunk of stream) {
        assistantContent += chunk;
        setMessages((prev) =>
          prev.map((msg) =>
            msg.id === assistantMessageId
              ? { ...msg, content: assistantContent }
              : msg
          )
        );
      }
    } catch (err: any) {
      if (err.name !== "AbortError") {
        console.error("Failed to get response:", err);
        setMessages((prev) =>
          prev.map((msg) =>
            msg.id === assistantMessageId
              ? { ...msg, content: "Sorry, I encountered an error. Please try again." }
              : msg
          )
        );
      }
    } finally {
      setLoading(false);
      setStreamingMessageId(null);
      setAbortController(null);
    }
  };

  const handleStopGeneration = () => {
    if (abortController) {
      abortController.abort();
    }
  };

  const handleRetryMessage = async (index: number) => {
    let lastUserMessageText = "";
    for (let i = index - 1; i >= 0; i--) {
      if (messages[i].role === "user") {
        lastUserMessageText = messages[i].content;
        break;
      }
    }

    if (!lastUserMessageText) return;

    const userMsgIdx = messages.findIndex((m) => m.content === lastUserMessageText && m.role === "user");
    if (userMsgIdx !== -1) {
      const slicedMessages = messages.slice(0, userMsgIdx + 1);
      setMessages(slicedMessages);
      
      // We call the send handler but temporarily bypass checking input state
      setLoading(true);
      const assistantMessageId = (Date.now() + 1).toString();
      setStreamingMessageId(assistantMessageId);
      const controller = new AbortController();
      setAbortController(controller);

      try {
        const newMessage: Message = {
          id: assistantMessageId,
          role: "assistant",
          content: "",
          timestamp: new Date(),
        };

        setMessages((prev) => [...prev, newMessage]);

        const chatMessages = [
          ...slicedMessages.map((m) => ({ role: m.role, content: m.content }))
        ];

        const stream = AIAPI.streamChat(chatMessages, controller.signal);
        let assistantContent = "";

        for await (const chunk of stream) {
          assistantContent += chunk;
          setMessages((prev) =>
            prev.map((msg) =>
              msg.id === assistantMessageId
                ? { ...msg, content: assistantContent }
                : msg
            )
          );
        }
      } catch (err: any) {
        if (err.name !== "AbortError") {
          console.error("Failed to get response:", err);
          setMessages((prev) =>
            prev.map((msg) =>
              msg.id === assistantMessageId
                ? { ...msg, content: "Sorry, I encountered an error. Please try again.", confidence: undefined, confidence: undefined }
                : msg
            )
          );
        }
      } finally {
        setLoading(false);
        setStreamingMessageId(null);
        setAbortController(null);
      }
    }
  };

  return (
    <AppLayout
      headerProps={{
        user: user ? {
          name: user.name,
          email: user.email,
          avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=" + user.email,
        } : undefined,
        onLogout: () => logout(),
      }}
    >
      <PageTransition>
        <div className="flex flex-col h-[calc(100vh-120px)] gap-6">
          {/* Header */}
          <div>
            <h1 className="page-title">AI Sales Assistant</h1>
            <p className="text-muted-foreground text-sm mt-1">
              Powered by enterprise AI. Real-time product recommendations and sales intelligence.
            </p>
          </div>

          {/* Chat Container */}
          <div className="flex-1 flex flex-col gap-4 min-h-0">
            {/* Messages Area */}
            <ScrollArea className="flex-1 border border-border rounded-lg bg-card/30 p-6">
              <div className="space-y-4">
                {messages.length === 1 && <SuggestedPrompts onSelectPrompt={handleSendMessage} />}

                {messages.map((message, idx) => (
                  <StreamingMessage
                    key={message.id}
                    content={message.content}
                    role={message.role}
                    isStreaming={streamingMessageId === message.id && loading}
                    confidence={message.confidence}
                    onRetry={() => handleRetryMessage(idx)}
                  />
                ))}

                <div ref={scrollRef} />
              </div>
            </ScrollArea>

            {/* Input Area */}
            <div className="flex gap-3">
              <div className="flex-1 flex gap-2">
                <Input
                  placeholder="Ask about products, leads, or quotes..."
                  value={input}
                  onChange={(e) => setInput(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter" && !e.shiftKey) {
                      e.preventDefault();
                      handleSendMessage();
                    }
                  }}
                  disabled={loading}
                  className="flex-1"
                />
                {loading ? (
                  <Button variant="destructive" size="icon" onClick={handleStopGeneration}>
                    <StopCircle className="h-4 w-4" />
                  </Button>
                ) : (
                  <Button
                    onClick={() => handleSendMessage()}
                    disabled={!input.trim()}
                    className="btn-primary"
                  >
                    <Send className="h-4 w-4" />
                  </Button>
                )}
              </div>
            </div>

            {/* Footer Info */}
            <div className="flex items-center justify-between text-xs text-muted-foreground">
              <div className="flex items-center gap-1">
                <Zap className="h-3 w-3 text-primary" />
                Powered by EckoX AI
              </div>
              <p>Conversations are saved automatically</p>
            </div>
          </div>
        </div>
      </PageTransition>
    </AppLayout>
  );
}
