export type Identity = { id: string; name: string; email: string; email_verified: boolean; permissions: string[] };
export type Settings = { name: string; tagline: string | null; contact_email: string | null; primary_color: string; accent_color: string };
export type Location = { id: string; name: string; slug: string; timezone: string; address: string | null; is_primary: boolean; is_public: boolean };
export type Feature = { key: string; state: "disabled" | "staff_preview" | "enabled" };
export type ServiceOccurrence = { id: string; title: string; timezone: string; starts_at: string; state: "draft" | "scheduled" | "ready" | "live" | "interrupted" | "ended" | "cancelled"; manual_stream_override: boolean; rehearsal_mode?: boolean };

function xsrfToken(): string {
  const cookie = document.cookie.split("; ").find((part) => part.startsWith("XSRF-TOKEN="));
  return cookie ? decodeURIComponent(cookie.slice("XSRF-TOKEN=".length)) : "";
}

export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const method = options.method?.toUpperCase() ?? "GET";
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (options.body && !(options.body instanceof FormData)) headers.set("Content-Type", "application/json");
  if (!["GET", "HEAD"].includes(method)) headers.set("X-XSRF-TOKEN", xsrfToken());
  const response = await fetch(`/api/v1${path}`, { ...options, headers, credentials: "same-origin" });
  if (!response.ok) {
    const payload = await response.json().catch(() => null);
    throw new Error(payload?.error?.message ?? payload?.message ?? `Request failed (${response.status}).`);
  }
  return response.status === 204 ? (undefined as T) : response.json();
}
