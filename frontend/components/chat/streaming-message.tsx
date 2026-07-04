"use client";

import { motion } from "framer-motion";
import { Copy, RotateCcw, Download } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useState } from "react";

interface StreamingMessageProps {
  content: string;
  role: "user" | "assistant";
  isStreaming?: boolean;
  confidence?: number;
  onRetry?: () => void;
}

export function StreamingMessage({
  content,
  role,
  isStreaming = false,
  confidence,
  onRetry,
}: StreamingMessageProps) {
  const [copied, setCopied] = useState(false);

  const handleCopy = () => {
    navigator.clipboard.writeText(content);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3 }}
      className={`flex gap-3 mb-4 ${role === "user" ? "justify-end" : "justify-start"}`}
    >
      <div
        className={`flex gap-3 max-w-2xl ${
          role === "user" ? "flex-row-reverse" : "flex-row"
        }`}
      >
        {/* Avatar */}
        <div
          className={`flex-shrink-0 h-8 w-8 rounded-full flex items-center justify-center text-xs font-semibold ${
            role === "user"
              ? "bg-primary text-primary-foreground"
              : "bg-secondary text-foreground"
          }`}
        >
          {role === "user" ? "You" : "AI"}
        </div>

        {/* Message Bubble */}
        <div
          className={`rounded-lg px-4 py-3 ${
            role === "user"
              ? "bg-primary text-primary-foreground rounded-br-none"
              : "bg-card border border-border rounded-bl-none"
          }`}
        >
          {content.length === 0 && isStreaming ? (
            <div className="flex gap-1 items-center py-2 px-1">
              <span className="w-2 h-2 rounded-full bg-muted-foreground/60 animate-bounce [animation-delay:-0.3s]"></span>
              <span className="w-2 h-2 rounded-full bg-muted-foreground/60 animate-bounce [animation-delay:-0.15s]"></span>
              <span className="w-2 h-2 rounded-full bg-muted-foreground/60 animate-bounce"></span>
            </div>
          ) : (
            <p className="text-sm leading-relaxed whitespace-pre-wrap break-words">
              {content}
            </p>
          )}
          {isStreaming && content.length > 0 && (
            <motion.span
              animate={{ opacity: [0, 1, 0] }}
              transition={{ duration: 0.8, repeat: Infinity }}
              className="inline-block ml-2 w-2 h-4 bg-current"
            />
          )}
          {confidence && role === "assistant" && (
            <p className="text-xs text-muted-foreground mt-2">
              Confidence: {confidence}%
            </p>
          )}
        </div>
      </div>

      {/* Message Actions */}
      {role === "assistant" && !isStreaming && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.2 }}
          className="flex flex-col gap-1"
        >
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            onClick={handleCopy}
            title={copied ? "Copied!" : "Copy message"}
          >
            <Copy className="h-3 w-3" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            title="Regenerate response"
            onClick={onRetry}
          >
            <RotateCcw className="h-3 w-3" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            title="Export to PDF"
          >
            <Download className="h-3 w-3" />
          </Button>
        </motion.div>
      )}
    </motion.div>
  );
}
