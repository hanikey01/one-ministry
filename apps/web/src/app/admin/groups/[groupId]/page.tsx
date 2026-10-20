"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { api } from "../../api";
import AdminShell from "../../AdminShell";

type Member = { id: string; person_id: string; role: string; first_name: string; last_name: string | null; email: string | null };
type Attendance = { id: string; gathered_at: string; title: string | null; attendance: Array<{ person_id: string; status: string; first_name: string; last_name: string | null }> };
type Person = { id: string; first_name: string; last_name: string | null; email: string | null };
type JoinRequest = { id: string; person_id: string | null; name: string; email: string | null; message: string | null; status: "pending" | "approved" | "declined"; created_at: string };

export default function GroupOperationsPage({ params }: { params: Promise<{ groupId: string }> }) {
  const [groupId, setGroupId] = useState("");
  const [members, setMembers] = useState<Member[]>([]);
  const [history, setHistory] = useState<Attendance[]>([]);
  const [selected, setSelected] = useState<string[]>([]);
  const [people, setPeople] = useState<Person[]>([]);
  const [joinRequests, setJoinRequests] = useState<JoinRequest[]>([]);
  const [addPersonId, setAddPersonId] = useState("");
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(true);

  async function load(id: string) {
    setBusy(true); setNotice("");
    try {
      const [memberResult, attendanceResult, peopleResult, requestResult] = await Promise.all([
        api<{ data: Member[] }>(`/staff/groups/${id}/members`),
        api<{ data: Attendance[] }>(`/staff/groups/${id}/attendance`),
        api<{ data: { data: Person[] } }>("/staff/people"),
        api<{ data: JoinRequest[] }>(`/staff/groups/${id}/join-requests`),
      ]);
      setMembers(memberResult.data); setHistory(attendanceResult.data); setPeople(peopleResult.data.data); setJoinRequests(requestResult.data);
    } catch (cause) { setNotice(cause instanceof Error ? cause.message : "Could not load group operations."); }
    finally { setBusy(false); }
  }

  useEffect(() => { void params.then(({ groupId: id }) => { setGroupId(id); void load(id); }); }, [params]);

  async function recordAttendance(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setNotice("");
    const form = new FormData(event.currentTarget);
    try {
      await api(`/staff/groups/${groupId}/attendance`, { method: "POST", body: JSON.stringify({ gathered_at: form.get("gathered_at"), title: form.get("title") || null, present_person_ids: selected }) });
      setSelected([]); event.currentTarget.reset(); setNotice("Attendance recorded."); await load(groupId);
    } catch (cause) { setNotice(cause instanceof Error ? cause.message : "Could not record attendance."); }
  }

  async function addMember(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!addPersonId) return; setNotice("");
    try { await api(`/staff/groups/${groupId}/members`, { method: "POST", body: JSON.stringify({ person_id: addPersonId }) }); setAddPersonId(""); setNotice("Member added to the group."); await load(groupId); }
    catch (cause) { setNotice(cause instanceof Error ? cause.message : "Could not add the member."); }
  }

  async function updateMember(member: Member, role?: "member" | "leader") {
    setNotice("");
    try {
      if (role) await api(`/staff/groups/${groupId}/members`, { method: "POST", body: JSON.stringify({ person_id: member.person_id, role }) });
      else await api(`/staff/groups/${groupId}/members/${member.id}`, { method: "DELETE" });
      setNotice(role ? "Member role updated." : "Member removed from the group."); await load(groupId);
    } catch (cause) { setNotice(cause instanceof Error ? cause.message : "Could not update this member."); }
  }

  async function reviewRequest(request: JoinRequest, status: "approved" | "declined") {
    setNotice("");
    try { await api(`/staff/groups/${groupId}/join-requests/${request.id}`, { method: "PATCH", body: JSON.stringify({ status }) }); setNotice(status === "approved" ? "Join request approved." : "Join request declined."); await load(groupId); }
    catch (cause) { setNotice(cause instanceof Error ? cause.message : "Could not review the join request."); }
  }

  return <AdminShell title="Group attendance" eyebrow="Community">{() => <main className="workspace-page detail-workspace">
    <Link href="/admin/groups" className="worship-back">← Groups</Link>
    <section className="workspace-panel"><p className="eyebrow">Community operations</p><h1>Group workspace</h1><p>Manage membership, leader assignments, public requests, and identified group participation in one place.</p></section>
    <div className="form-grid group-management-grid">
      <section className="workspace-panel"><div className="section-title"><div><p className="eyebrow">Membership</p><h2>Group roster</h2></div><p>{members.length} current member{members.length === 1 ? "" : "s"}</p></div><form className="quick-add-form" onSubmit={addMember}><label>Add an existing person<select value={addPersonId} onChange={event => setAddPersonId(event.target.value)} required><option value="">Choose a person</option>{people.filter(person => !members.some(member => member.person_id === person.id)).map(person => <option key={person.id} value={person.id}>{[person.first_name, person.last_name].filter(Boolean).join(" ")}{person.email ? ` · ${person.email}` : ""}</option>)}</select></label><button className="workspace-primary-button" disabled={busy || !addPersonId}>Add member</button></form><div className="operations-list">{members.map(member => <article key={member.id}><strong>{[member.first_name, member.last_name].filter(Boolean).join(" ")}</strong><span>{member.email || "No email"} · {member.role}</span><span><button className="workspace-secondary-button" disabled={busy} onClick={() => void updateMember(member, member.role === "leader" ? "member" : "leader")}>{member.role === "leader" ? "Make member" : "Make leader"}</button><button className="text-button" disabled={busy} onClick={() => void updateMember(member)}>Remove</button></span></article>)}{!members.length && !busy && <p>No group members yet.</p>}</div></section>
      <section className="workspace-panel"><div className="section-title"><div><p className="eyebrow">Public discovery</p><h2>Join requests</h2></div><p>{joinRequests.filter(request => request.status === "pending").length} pending</p></div><div className="operations-list">{joinRequests.map(request => <article key={request.id}><strong>{request.name}</strong><span>{request.email || "No email"} · {request.status}</span>{request.message && <p>{request.message}</p>}{request.status === "pending" && <span><button className="workspace-primary-button" disabled={busy} onClick={() => void reviewRequest(request, "approved")}>Approve</button><button className="workspace-secondary-button" disabled={busy} onClick={() => void reviewRequest(request, "declined")}>Decline</button></span>}</article>)}{!joinRequests.length && !busy && <p>No join requests yet.</p>}</div></section>
    </div>
    <div className="form-grid">
      <section className="workspace-panel"><h2>Record a gathering</h2><form className="quick-add-form" onSubmit={recordAttendance}><label>Date and time<input name="gathered_at" type="datetime-local" required /></label><label>Gathering title <span>(optional)</span><input name="title" maxLength={160} /></label><fieldset><legend>Members present</legend>{members.map(member => <label className="checkbox-label" key={member.person_id}><input type="checkbox" checked={selected.includes(member.person_id)} onChange={event => setSelected(current => event.target.checked ? [...current, member.person_id] : current.filter(id => id !== member.person_id))} /> {[member.first_name, member.last_name].filter(Boolean).join(" ")} · {member.role}</label>)}{!members.length && !busy && <p>No current members.</p>}</fieldset><button className="workspace-primary-button" disabled={busy}>Save attendance</button></form></section>
      <section className="workspace-panel"><h2>Recent gatherings</h2><div className="operations-list">{history.map(gathering => <article key={gathering.id}><strong>{gathering.title || "Group gathering"}</strong><span>{new Date(gathering.gathered_at).toLocaleString()} · {gathering.attendance.length} present</span><p>{gathering.attendance.map(record => [record.first_name, record.last_name].filter(Boolean).join(" ")).join(", ") || "No attendance recorded"}</p></article>)}{!history.length && !busy && <p>No gatherings recorded yet.</p>}</div></section>
    </div>{notice && <p className="catalog-notice" role="status">{notice}</p>}
  </main>}</AdminShell>;
}
