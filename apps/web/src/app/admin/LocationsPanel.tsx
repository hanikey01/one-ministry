"use client";

import { FormEvent, useEffect, useState } from "react";
import { api, type Location } from "./api";

export default function LocationsPanel({ canManage }: { canManage: boolean }) {
  const [locations, setLocations] = useState<Location[]>([]);
  const [name, setName] = useState("");
  const [timezone, setTimezone] = useState("UTC");
  const [address, setAddress] = useState("");
  const [isPublic, setIsPublic] = useState(true);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [editing, setEditing] = useState<Location | null>(null);

  useEffect(() => {
    api<{ data: Location[] }>("/staff/locations").then(response => setLocations(response.data))
      .catch(cause => setError(cause instanceof Error ? cause.message : "Could not load locations."));
  }, []);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError("");
    try {
      const response = await api<{ data: Location }>("/staff/locations", {
        method: "POST", body: JSON.stringify({ name, timezone, address: address || null, is_public: isPublic }),
      });
      setLocations([...locations, response.data]); setName(""); setAddress("");
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not create location."); }
    finally { setBusy(false); }
  }

  async function update(location: Location, changes: Partial<Location>) {
    setBusy(true); setError("");
    try {
      const response = await api<{ data: Location }>(`/staff/locations/${location.id}`, {
        method: "PATCH", body: JSON.stringify(changes),
      });
      setLocations(locations.map(item => item.id === location.id ? response.data : changes.is_primary ? { ...item, is_primary: false } : item));
      setEditing(null);
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not update location."); }
    finally { setBusy(false); }
  }

  return <section className="admin-section"><h2>Locations</h2><p>Choose which branches visitors can find on the public site.</p>
    <div className="admin-list">{locations.map(location => <div className="admin-list-item" key={location.id}>
      <div><strong>{location.name}</strong><span>{location.timezone}{location.address ? ` · ${location.address}` : ""}</span></div>
      <div className="item-actions"><span>{location.is_primary ? "Primary" : ""}</span>
        {canManage && !location.is_primary && <button className="text-button" disabled={busy} onClick={() => update(location, { is_primary: true })}>Make primary</button>}
        {canManage && <button className="text-button" disabled={busy} onClick={() => update(location, { is_public: !location.is_public })}>{location.is_public ? "Hide from site" : "Show on site"}</button>}
        {canManage && <button className="text-button" disabled={busy} onClick={() => setEditing(location)}>Edit</button>}
      </div>
    </div>)}</div>
    {editing && <form className="admin-form" onSubmit={event => { event.preventDefault(); void update(editing, { name: editing.name, timezone: editing.timezone, address: editing.address }); }}>
      <h3>Edit {editing.name}</h3>
      <label>Name<input value={editing.name} onChange={event => setEditing({ ...editing, name: event.target.value })} maxLength={160} required /></label>
      <label>Time zone<input value={editing.timezone} onChange={event => setEditing({ ...editing, timezone: event.target.value })} required /></label>
      <label>Address (optional)<input value={editing.address ?? ""} onChange={event => setEditing({ ...editing, address: event.target.value || null })} maxLength={2000} /></label>
      <div className="item-actions"><button className="button button-primary" disabled={busy}>Save location</button><button type="button" className="text-button" onClick={() => setEditing(null)}>Cancel</button></div>
    </form>}
    {canManage && <form className="admin-form" onSubmit={create}><h3>Add a location</h3>
      <label>Name<input value={name} onChange={event => setName(event.target.value)} maxLength={160} required /></label>
      <label>Time zone<input value={timezone} onChange={event => setTimezone(event.target.value)} placeholder="Africa/Lagos" required /></label>
      <label>Address (optional)<input value={address} onChange={event => setAddress(event.target.value)} maxLength={2000} /></label>
      <label className="checkbox-label"><input type="checkbox" checked={isPublic} onChange={event => setIsPublic(event.target.checked)} /> Show on public site</label>
      <button className="button button-primary" disabled={busy}>{busy ? "Saving…" : "Add location"}</button>
    </form>}
    {error && <p className="form-error" role="alert">{error}</p>}
  </section>;
}
