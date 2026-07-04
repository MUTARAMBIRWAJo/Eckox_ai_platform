"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { ScrollArea } from "@/components/ui/scroll-area";
import { conversationsAPI, authAPI, AuthUser } from "@/lib/api";
import { Mail, MessageSquare, Phone } from "lucide-react";

interface Conversation {
  id: string;
  leadId: string;
  channel: string;
  lastMessage: string;
  lastMessageTime: string;
  unread: boolean;
}

export default function ConversationsPage() {
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [selectedConv, setSelectedConv] = useState<Conversation | null>(null);
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadData = async () => {
      try {
        const [currentUser, convs] = await Promise.all([
          authAPI.getCurrentUser(),
          conversationsAPI.getConversations(20),
        ]);
        setUser(currentUser);
        setConversations(convs);
        if (convs.length > 0) setSelectedConv(convs[0]);
      } catch (err) {
        console.error("Failed to load conversations:", err);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

  const getChannelIcon = (channel: string) => {
    switch (channel) {
      case "email":
        return <Mail className="w-4 h-4" />;
      case "sms":
        return <MessageSquare className="w-4 h-4" />;
      case "call":
        return <Phone className="w-4 h-4" />;
      default:
        return <MessageSquare className="w-4 h-4" />;
    }
  };

  return (
    <AppLayout
      headerProps={{
        user,
        onLogout: () => window.location.href = "/login",
      }}
    >
      <div className="space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-bold">Omnichannel Conversations</h1>
          <p className="text-muted-foreground mt-1">Manage all conversations across email, SMS, and calls</p>
        </div>

        {/* Conversations View */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[calc(100vh-280px)]">
          {/* Conversations List */}
          <Card className="card col-span-1 overflow-hidden flex flex-col">
            <CardHeader className="border-b border-border">
              <CardTitle>Messages</CardTitle>
              <CardDescription>{conversations.length} conversations</CardDescription>
            </CardHeader>
            <ScrollArea className="flex-1">
              <div className="space-y-1 p-4">
                {conversations.map((conv) => (
                  <button
                    key={conv.id}
                    onClick={() => setSelectedConv(conv)}
                    className={`w-full text-left p-3 rounded-lg transition-colors ${
                      selectedConv?.id === conv.id
                        ? "bg-primary/20 border border-primary"
                        : "hover:bg-secondary"
                    }`}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex items-center gap-2 flex-1 min-w-0">
                        <Avatar className="w-8 h-8 flex-shrink-0">
                          <AvatarFallback>C</AvatarFallback>
                        </Avatar>
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-medium truncate">{conv.leadId}</p>
                          <p className="text-xs text-muted-foreground truncate">{conv.lastMessage}</p>
                        </div>
                      </div>
                      {conv.unread && <div className="w-2 h-2 bg-primary rounded-full flex-shrink-0" />}
                    </div>
                  </button>
                ))}
              </div>
            </ScrollArea>
          </Card>

          {/* Conversation Detail */}
          {selectedConv ? (
            <Card className="card col-span-1 lg:col-span-2 overflow-hidden flex flex-col">
              <CardHeader className="border-b border-border flex-row items-center justify-between space-y-0">
                <div>
                  <CardTitle>{selectedConv.leadId}</CardTitle>
                  <CardDescription className="flex items-center gap-2 mt-1">
                    {getChannelIcon(selectedConv.channel)}
                    {selectedConv.channel}
                  </CardDescription>
                </div>
                <Badge>{selectedConv.channel}</Badge>
              </CardHeader>
              <ScrollArea className="flex-1 p-4">
                <div className="space-y-4">
                  {Array.from({ length: 6 }).map((_, idx) => (
                    <div
                      key={idx}
                      className={`flex gap-3 ${idx % 2 === 0 ? "justify-end" : ""}`}
                    >
                      {idx % 2 === 0 ? null : (
                        <Avatar className="w-8 h-8 flex-shrink-0">
                          <AvatarFallback>C</AvatarFallback>
                        </Avatar>
                      )}
                      <div
                        className={`max-w-xs px-4 py-2 rounded-lg ${
                          idx % 2 === 0
                            ? "bg-primary text-primary-foreground rounded-br-none"
                            : "bg-secondary text-foreground rounded-bl-none"
                        }`}
                      >
                        <p className="text-sm">Message content {idx + 1}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </ScrollArea>
              <div className="border-t border-border p-4">
                <input
                  type="text"
                  placeholder="Type a message..."
                  className="input-base w-full"
                />
              </div>
            </Card>
          ) : (
            <Card className="card col-span-1 lg:col-span-2 flex items-center justify-center">
              <div className="text-center text-muted-foreground">
                <MessageSquare className="w-12 h-12 mx-auto mb-4 opacity-50" />
                <p>Select a conversation to start messaging</p>
              </div>
            </Card>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
