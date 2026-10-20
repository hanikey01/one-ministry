"use client";

import { useEffect, useState } from "react";
import { api, type Feature } from "./api";

const labels: Record<string, string> = {
  digital_church: "Digital church", people: "People", groups: "Groups", giving: "Giving",
};

export default function FeaturesPanel({ canManage }: { canManage: boolean }) {
  const [features, setFeatures] = useState<Feature[]>([]);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api<{ data: Feature[] }>("/staff/features").then(response => setFeatures(response.data))
      .catch(cause => setError(cause instanceof Error ? cause.message : "Could not load modules."));
  }, []);

  async function change(feature: Feature, state: "disabled" | "staff_preview") {
    setBusy(true); setError("");
    try {
      const response = await api<{ data: Feature }>(`/staff/features/${feature.key}`, {
        method: "PATCH", body: JSON.stringify({ state }),
      });
      setFeatures(features.map(item => item.key === feature.key ? response.data : item));
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not change module state."); }
    finally { setBusy(false); }
  }

  return <section className="admin-section"><h2>Modules</h2><p>Staff preview keeps unfinished modules unavailable to the public.</p>
    <div className="admin-list">{features.map(feature => <div className="admin-list-item" key={feature.key}>
      <div><strong>{labels[feature.key] ?? feature.key}</strong><span>{feature.state === "staff_preview" ? "Staff preview" : feature.state === "enabled" ? "Public" : "Disabled"}</span></div>
      {canManage && <button className="text-button" disabled={busy} onClick={() => change(feature, feature.state === "disabled" ? "staff_preview" : "disabled")}>{feature.state === "disabled" ? "Set staff preview" : "Disable"}</button>}
    </div>)}</div>
    {error && <p className="form-error" role="alert">{error}</p>}
  </section>;
}
