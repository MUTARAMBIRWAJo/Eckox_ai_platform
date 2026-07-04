"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { notificationsAPI, authAPI, AuthUser } from "@/lib/api";
import { Trash2, Check } from "lucide-react";

interface Notification {
  id: string;
  type: string;
  title: string;
  message: string;
  timestamp: string;
  read: boolean;
}

export default function NotificationsPage() {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadData = async () => {
      try {
        const [currentUser, notifs] = await Promise.all([
          authAPI.getCurrentUser(),
          notificationsAPI.getNotifications(20),
        ]);
        setUser(currentUser);
        setNotifications(notifs);
      } catch (err) {
        console.error("Failed to load notifications:", err);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

  const markAsRead = async (id: string) => {
    await notificationsAPI.markAsRead(id);
    setNotifications((prevs) =>
      prevs.map((notif) =>
        notif.id === id ? { ...notif, read: true } : notif
      )
    );
  };

  const deleteNotification = (id: string) => {
    setNotifications((prevs) => prevs.filter((notif) => notif.id !== id));
  };

  const getTypeColor = (type: string) => {
    const colors: Record<string, string> = {
      lead_update: "bg-blue-500/10 text-blue-400",
      quote_sent: "bg-green-500/10 text-green-400",
      task_due: "bg-yellow-500/10 text-yellow-400",
      message: "bg-purple-500/10 text-purple-400",
    };
    return colors[type] || "bg-secondary";
  };

  const unreadCount = notifications.filter((n) => !n.read).length;

  return (
    <AppLayout
      headerProps={{
        user,
        onLogout: () => window.location.href = "/login",
      }}
    >
      <div className="space-y-6 max-w-2xl">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold">Notifications</h1>
            <p className="text-muted-foreground mt-1">
              You have {unreadCount} unread notification{unreadCount !== 1 ? "s" : ""}
            </p>
          </div>
          {unreadCount > 0 && (
            <Button 
              variant="outline"
              onClick={() => {
                setNotifications((prevs) =>
                  prevs.map((notif) => ({ ...notif, read: true }))
                );
              }}
            >
              Mark All as Read
            </Button>
          )}
        </div>

        {/* Notifications List */}
        <div className="space-y-3">
          {loading ? (
            Array.from({ length: 5 }).map((_, i) => (
              <div key={i} className="card p-4 animate-pulse">
                <div className="h-4 bg-secondary rounded w-3/4 mb-2" />
                <div className="h-3 bg-secondary rounded w-1/2" />
              </div>
            ))
          ) : notifications.length === 0 ? (
            <Card className="card">
              <CardContent className="pt-12 pb-12 text-center">
                <p className="text-muted-foreground">No notifications</p>
              </CardContent>
            </Card>
          ) : (
            notifications.map((notif) => (
              <Card
                key={notif.id}
                className={`card transition-all ${!notif.read ? "border-primary/50 bg-primary/5" : ""}`}
              >
                <CardContent className="pt-6 flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-2">
                      <Badge className={getTypeColor(notif.type)}>
                        {notif.type.replace("_", " ")}
                      </Badge>
                      {!notif.read && (
                        <div className="w-2 h-2 bg-primary rounded-full" />
                      )}
                    </div>
                    <h3 className="font-semibold">{notif.title}</h3>
                    <p className="text-sm text-muted-foreground mt-1">{notif.message}</p>
                    <p className="text-xs text-muted-foreground mt-2">
                      {new Date(notif.timestamp).toLocaleString()}
                    </p>
                  </div>
                  <div className="flex gap-2 flex-shrink-0">
                    {!notif.read && (
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => markAsRead(notif.id)}
                        title="Mark as read"
                      >
                        <Check className="w-4 h-4" />
                      </Button>
                    )}
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => deleteNotification(notif.id)}
                    >
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))
          )}
        </div>
      </div>
    </AppLayout>
  );
}
