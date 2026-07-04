"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { useAuth } from "@/hooks/use-auth";
import { User, Bell, Lock, Zap, Globe } from "lucide-react";

interface SettingsSection {
  icon: React.ReactNode;
  title: string;
  description: string;
  id: string;
}

const SETTINGS_SECTIONS: SettingsSection[] = [
  {
    icon: <User className="w-5 h-5" />,
    title: "Profile",
    description: "Update your profile information",
    id: "profile",
  },
  {
    icon: <Bell className="w-5 h-5" />,
    title: "Notifications",
    description: "Manage notification preferences",
    id: "notifications",
  },
  {
    icon: <Lock className="w-5 h-5" />,
    title: "Security",
    description: "Password and security settings",
    id: "security",
  },
  {
    icon: <Zap className="w-5 h-5" />,
    title: "Integrations",
    description: "Connect external tools",
    id: "integrations",
  },
  {
    icon: <Globe className="w-5 h-5" />,
    title: "Preferences",
    description: "Language and regional settings",
    id: "preferences",
  },
];

export default function SettingsPage() {
  const { user, updateProfile, logout } = useAuth();
  const [activeTab, setActiveTab] = useState("profile");
  
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  const [notificationSettings, setNotificationSettings] = useState({
    emailOnLeadUpdate: true,
    emailOnQuoteSent: true,
    emailOnDealClosed: true,
    slackNotifications: false,
    dailySummary: true,
  });

  // Sync state with user info
  useEffect(() => {
    if (user) {
      setName(user.name || "");
      setEmail(user.email || "");
    }
  }, [user]);

  const handleProfileSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setMessage(null);
    
    try {
      const success = await updateProfile({ name, email });
      if (success) {
        setMessage({ type: 'success', text: 'Profile updated successfully' });
      } else {
        setMessage({ type: 'error', text: 'Failed to update profile' });
      }
    } catch (err) {
      setMessage({ type: 'error', text: 'An error occurred during profile update' });
    } finally {
      setLoading(false);
    }
  };

  const handlePasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!password) {
      setMessage({ type: 'error', text: 'Please enter a new password' });
      return;
    }
    if (password !== confirmPassword) {
      setMessage({ type: 'error', text: 'Passwords do not match' });
      return;
    }
    if (password.length < 8) {
      setMessage({ type: 'error', text: 'Password must be at least 8 characters' });
      return;
    }

    setLoading(true);
    setMessage(null);

    try {
      const success = await updateProfile({
        name,
        email,
        password,
        password_confirmation: confirmPassword
      });
      if (success) {
        setMessage({ type: 'success', text: 'Password updated successfully' });
        setPassword("");
        setConfirmPassword("");
      } else {
        setMessage({ type: 'error', text: 'Failed to update password' });
      }
    } catch (err) {
      setMessage({ type: 'error', text: 'An error occurred during password update' });
    } finally {
      setLoading(false);
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
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold">Settings</h1>
            <p className="text-muted-foreground mt-1">Manage your account and preferences</p>
          </div>
          {message && (
            <div className={`px-4 py-2 rounded-lg border text-sm font-medium ${
              message.type === 'success' 
                ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-500' 
                : 'bg-destructive/10 border-destructive/20 text-destructive'
            }`}>
              {message.text}
            </div>
          )}
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
          {/* Sidebar Navigation */}
          <div className="lg:col-span-1">
            <div className="space-y-1 sticky top-6">
              {SETTINGS_SECTIONS.map((section) => (
                <button
                  key={section.id}
                  onClick={() => {
                    setActiveTab(section.id);
                    setMessage(null);
                  }}
                  className={`w-full text-left px-4 py-3 rounded-lg transition-colors flex items-center gap-3 ${
                    activeTab === section.id
                      ? "bg-primary/20 border border-primary text-foreground"
                      : "hover:bg-secondary"
                  }`}
                >
                  {section.icon}
                  <span className="font-medium text-sm">{section.title}</span>
                </button>
              ))}
            </div>
          </div>

          {/* Main Content */}
          <div className="lg:col-span-3">
            {activeTab === "profile" && (
              <Card className="card">
                <CardHeader>
                  <CardTitle>Profile Settings</CardTitle>
                  <CardDescription>Update your personal information</CardDescription>
                </CardHeader>
                <form onSubmit={handleProfileSubmit}>
                  <CardContent className="space-y-6">
                    <div className="space-y-2">
                      <label className="label-base">Full Name</label>
                      <Input
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        disabled={loading}
                        required
                        className="bg-background"
                      />
                    </div>
                    <div className="space-y-2">
                      <label className="label-base">Email</label>
                      <Input
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        disabled={loading}
                        required
                        className="bg-background"
                      />
                    </div>
                    <div className="space-y-2">
                      <label className="label-base">Assigned Roles</label>
                      <div className="flex flex-wrap gap-2 pt-1">
                        {user?.roles && user.roles.length > 0 ? (
                          user.roles.map((r) => (
                            <span key={r} className="px-2.5 py-0.5 rounded-full bg-primary/20 text-xs font-semibold text-primary capitalize">
                              {r}
                            </span>
                          ))
                        ) : (
                          <span className="text-xs text-muted-foreground">No roles assigned</span>
                        )}
                      </div>
                    </div>
                    <div className="pt-4">
                      <Button type="submit" disabled={loading} className="btn-primary">
                        {loading ? 'Saving...' : 'Save Changes'}
                      </Button>
                    </div>
                  </CardContent>
                </form>
              </Card>
            )}

            {activeTab === "notifications" && (
              <Card className="card">
                <CardHeader>
                  <CardTitle>Notification Preferences</CardTitle>
                  <CardDescription>Choose how you want to be notified</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                  {Object.entries(notificationSettings).map(([key, value]) => (
                    <div key={key} className="flex items-center justify-between py-3 border-b border-border last:border-0">
                      <div>
                        <p className="font-medium text-sm capitalize">
                          {key.replace(/([A-Z])/g, " $1").toLowerCase()}
                        </p>
                      </div>
                      <Switch
                        checked={value}
                        onCheckedChange={(checked) =>
                          setNotificationSettings((prev) => ({
                            ...prev,
                            [key]: checked,
                          }))
                        }
                      />
                    </div>
                  ))}
                  <div className="pt-4">
                    <Button className="btn-primary">Save Preferences</Button>
                  </div>
                </CardContent>
              </Card>
            )}

            {activeTab === "security" && (
              <Card className="card">
                <CardHeader>
                  <CardTitle>Security</CardTitle>
                  <CardDescription>Manage your security settings</CardDescription>
                </CardHeader>
                <form onSubmit={handlePasswordSubmit}>
                  <CardContent className="space-y-6">
                    <div className="space-y-2">
                      <label className="label-base">New Password</label>
                      <Input
                        type="password"
                        placeholder="Min 8 characters"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        disabled={loading}
                        required
                        className="bg-background"
                      />
                    </div>
                    <div className="space-y-2">
                      <label className="label-base">Confirm Password</label>
                      <Input
                        type="password"
                        placeholder="••••••••"
                        value={confirmPassword}
                        onChange={(e) => setConfirmPassword(e.target.value)}
                        disabled={loading}
                        required
                        className="bg-background"
                      />
                    </div>
                    <div className="pt-4">
                      <Button type="submit" disabled={loading} className="btn-primary">
                        {loading ? 'Updating...' : 'Update Password'}
                      </Button>
                    </div>
                  </CardContent>
                </form>
              </Card>
            )}

            {activeTab === "integrations" && (
              <Card className="card">
                <CardHeader>
                  <CardTitle>Integrations</CardTitle>
                  <CardDescription>Connect third-party tools</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <p className="text-muted-foreground">No integrations connected yet.</p>
                  <Button className="btn-primary">Connect Integration</Button>
                </CardContent>
              </Card>
            )}

            {activeTab === "preferences" && (
              <Card className="card">
                <CardHeader>
                  <CardTitle>Preferences</CardTitle>
                  <CardDescription>Customize your experience</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                  <div>
                    <label className="label-base">Language</label>
                    <select className="input-base mt-2 w-full">
                      <option>English</option>
                      <option>Spanish</option>
                      <option>French</option>
                      <option>German</option>
                    </select>
                  </div>
                  <div>
                    <label className="label-base">Timezone</label>
                    <select className="input-base mt-2 w-full">
                      <option>UTC</option>
                      <option>EST</option>
                      <option>CST</option>
                      <option>PST</option>
                    </select>
                  </div>
                  <div className="pt-4">
                    <Button className="btn-primary">Save Preferences</Button>
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
