"use client";

import { FormEvent, useEffect, useRef, useState } from "react";
import Link from "next/link";

type Service = { id: string; title: string; state: "live" | "interrupted"; starts_at: string; timezone: string; media: { state: "available" | "degraded" | "unavailable"; message: string; playback_type: "youtube" | "vimeo" | "hls" | null; playback_url: string | null; lower_bandwidth_url: string | null; captions_url: string | null }; chat_enabled: boolean };
type Message = { id: string; display_name: string; is_host: boolean; content: string; created_at: string };
type Poll = { id: string; question: string; status: "open" | "closed"; options: Array<{ id: string; label: string; responses?: number }> };

function guestKey(serviceId: string): string {
  const key = `ministry-poll-guest-${serviceId}`;
  const current = localStorage.getItem(key);
  if (current) return current;
  const created = crypto.randomUUID();
  localStorage.setItem(key, created);
  return created;
}

function playbackKey(serviceId:string):string{const key=`ministry-playback-session-${serviceId}`;const current=sessionStorage.getItem(key);if(current)return current;const created=crypto.randomUUID();sessionStorage.setItem(key,created);return created}

export default function WorshipRoom({ serviceId }: { serviceId: string }) {
  const [service, setService] = useState<Service | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [polls, setPolls] = useState<Poll[]>([]);
  const [name, setName] = useState("");
  const [content, setContent] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [action, setAction] = useState<"newcomer" | "prayer" | "speak" | null>(null);
  const [notice, setNotice] = useState("");
  const [answeredPolls, setAnsweredPolls] = useState<string[]>([]);
  const [lowBandwidth, setLowBandwidth] = useState(false);
  const [chatPolicy, setChatPolicy] = useState({ paused: false, slow_mode_seconds: 0 });
  const openedAt = useRef(Date.now());
  const stallAt = useRef<number|null>(null);
  const playbackStarted = useRef(false);
  const lowBandwidthRef = useRef(false);
  lowBandwidthRef.current = lowBandwidth;

  function reportPlayback(event_type:string,extra:Record<string,unknown>={}){if(!service)return;const body=JSON.stringify({event_id:crypto.randomUUID(),session_key:playbackKey(serviceId),event_type,playback_type:service.media.playback_type,quality:lowBandwidthRef.current?"low":"standard",...extra});void fetch(`/api/v1/public/services/${serviceId}/playback-diagnostics`,{method:"POST",headers:{"Content-Type":"application/json"},body,keepalive:true}).catch(()=>undefined)}

  async function load() {
    const response = await fetch(`/api/v1/public/services/${serviceId}`, { cache: "no-store" });
    if (!response.ok) throw Error("This worship room is not available.");
    const payload: { data: Service } = await response.json();
    setService(payload.data);
    if (payload.data.chat_enabled) {
      const chat = await fetch(`/api/v1/public/services/${serviceId}/chat`);
      if (chat.ok) { const chatPayload = await chat.json(); setMessages(chatPayload.data); setChatPolicy(chatPayload.meta ?? { paused: false, slow_mode_seconds: 0 }); }
      const poll = await fetch(`/api/v1/public/services/${serviceId}/polls`);
      if (poll.ok) setPolls((await poll.json()).data);
    }
  }

  useEffect(() => {
    void load().catch(cause => setError(cause instanceof Error ? cause.message : "Could not load worship room."));
    const timer = window.setInterval(() => void load().catch(() => undefined), 15000);
    return () => window.clearInterval(timer);
  }, [serviceId]);
  useEffect(()=>{if(!service)return;reportPlayback("room_opened");const heartbeat=window.setInterval(()=>reportPlayback("heartbeat"),30000);const ending=()=>{const body=JSON.stringify({event_id:crypto.randomUUID(),session_key:playbackKey(serviceId),event_type:"playback_ended",playback_type:service.media.playback_type,quality:lowBandwidthRef.current?"low":"standard"});navigator.sendBeacon?.(`/api/v1/public/services/${serviceId}/playback-diagnostics`,new Blob([body],{type:"application/json"}))};window.addEventListener("pagehide",ending);return()=>{window.clearInterval(heartbeat);window.removeEventListener("pagehide",ending)}},[service?.id]);

  async function send(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError("");
    try {
      const response = await fetch(`/api/v1/public/services/${serviceId}/chat`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ display_name: name, content, guest_key: guestKey(serviceId) }) });
      if (!response.ok) throw Error("Your message could not be sent.");
      const payload: { data: Message } = await response.json();
      setMessages(current => [...current, payload.data]); setContent("");
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not send message."); }
    finally { setBusy(false); }
  }

  async function respond(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError("");
    const form = new FormData(event.currentTarget);
    const prayer = action === "prayer";
    const body = prayer
      ? { prayer_text: String(form.get("prayer_text")), display_name: String(form.get("display_name") || ""), contact_email: String(form.get("contact_email") || "") }
      : { kind: action === "newcomer" ? "newcomer" : "speak_to_someone", name: String(form.get("name")), email: String(form.get("email") || "") };
    try {
      const response = await fetch(`/api/v1/public/services/${serviceId}/${prayer ? "prayer-requests" : "responses"}`, { method: "POST", headers: { "Content-Type": "application/json", "Idempotency-Key": crypto.randomUUID() }, body: JSON.stringify(body) });
      if (!response.ok) throw Error("We could not receive your request.");
      setNotice(prayer ? "Your prayer request was received privately." : "Thank you. A ministry team member will follow up.");
      setAction(null);
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not send request."); }
    finally { setBusy(false); }
  }

  async function answer(poll: Poll, optionId: string) {
    setBusy(true); setError("");
    try {
      const response = await fetch(`/api/v1/public/services/${serviceId}/polls/${poll.id}/responses`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ option_id: optionId, guest_key: guestKey(serviceId) }) });
      if (!response.ok) throw Error("Your poll response could not be recorded.");
      setAnsweredPolls(current => [...current, poll.id]);
      const result: { data: Poll } = await response.json();
      setPolls(current => current.map(item => item.id === poll.id ? result.data : item));
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not answer this poll."); }
    finally { setBusy(false); }
  }

  if (error && !service) return <main className="worship-page"><div className="worship-error"><h1>{error}</h1><Link className="button button-primary" href="/">Return to homepage</Link></div></main>;
  if (!service) return <main className="worship-page"><div className="worship-loading">Preparing the worship room…</div></main>;

  return <main className="worship-page">
    <header className="worship-header"><Link href="/" className="brand"><span className="brand-mark">✦</span>Ministry Platform</Link><Link href="/" className="worship-back">← Back to ministry</Link></header>
    <div className="worship-layout">
      <section className="worship-player">
        {service.state === "live" && service.media.state !== "unavailable" && service.media.playback_url ? <div className="live-player"><div className="live-player-badge"><span className="live-dot">LIVE</span>{service.media.state === "degraded" && <b>Reduced quality</b>}</div>{service.media.playback_type === "hls" ? <video key={lowBandwidth ? service.media.lower_bandwidth_url : service.media.playback_url} controls autoPlay playsInline src={lowBandwidth && service.media.lower_bandwidth_url ? service.media.lower_bandwidth_url : service.media.playback_url} onPlaying={()=>{const recovered=stallAt.current!==null;if(!playbackStarted.current){playbackStarted.current=true;reportPlayback("playback_started",{startup_ms:Date.now()-openedAt.current})}else if(recovered){reportPlayback("playback_recovered",{rebuffer_ms:Date.now()-(stallAt.current??Date.now())})}stallAt.current=null}} onWaiting={()=>{if(stallAt.current===null){stallAt.current=Date.now();reportPlayback("playback_stalled")}}} onError={()=>reportPlayback("playback_failed")}>{service.media.captions_url && <track default kind="captions" src={service.media.captions_url} srcLang="en" label="Captions" />}</video> : <iframe src={service.media.playback_url} title={`${service.title} live video`} allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowFullScreen referrerPolicy="strict-origin-when-cross-origin" onLoad={()=>reportPlayback("player_loaded",{startup_ms:Date.now()-openedAt.current})}/>}</div> : <div className="player-fallback"><span className={service.state === "live" ? "live-dot" : ""}>{service.state === "live" ? "LIVE UPDATE" : "SERVICE UPDATE"}</span><h1>{service.title}</h1><p>{service.media.message}</p><button className="button button-light" onClick={() => void load()}>Check stream again</button></div>}
        <div className="worship-actions"><span>{service.state === "live" ? service.media.message : "The stream is temporarily interrupted"}</span><span>{service.media.playback_type === "hls" && service.media.lower_bandwidth_url && <button className="bandwidth-button" onClick={() => setLowBandwidth(value => !value)}>{lowBandwidth ? "Use standard quality" : "Use lower bandwidth"}</button>}{new Date(service.starts_at).toLocaleString([], { dateStyle: "medium", timeStyle: "short" })} · {service.timezone}</span></div>
        <div className="worship-response-actions"><p className="eyebrow">Take your next step</p><button className="response-button" onClick={() => setAction("newcomer")}>I’m new here</button><button className="response-button" onClick={() => setAction("prayer")}>Request prayer</button><button className="response-button" onClick={() => setAction("speak")}>Speak to someone</button>{notice && <p className="form-success">{notice}</p>}
          {action && <form className="response-panel" onSubmit={respond}><h2>{action === "prayer" ? "Share a prayer request" : "Connect with our team"}</h2>{action === "prayer" ? <><label>Prayer request<textarea name="prayer_text" required maxLength={4000} /></label><label>Name <span>(optional)</span><input name="display_name" /></label><label>Email <span>(optional)</span><input name="contact_email" type="email" /></label></> : <><label>Your name<input name="name" required /></label><label>Email <span>(optional)</span><input name="email" type="email" /></label></>}<button className="button button-primary" disabled={busy}>{busy ? "Sending…" : "Send request"}</button></form>}
        </div>
      </section>
      <aside className="worship-chat">
        <div className="chat-heading"><div><p className="eyebrow">Community</p><h2>Service chat</h2></div><span>{messages.length}</span></div>
        {service.chat_enabled ? <><div className="chat-messages">{messages.length ? messages.map(message => <div className={message.is_host ? "chat-message host" : "chat-message"} key={message.id}><strong>{message.display_name}{message.is_host&&<span>Host</span>}</strong><p>{message.content}</p></div>) : <p className="chat-empty">Be the first to welcome everyone.</p>}</div>
          {polls.length > 0 && <div className="worship-polls">{polls.map(poll => <article key={poll.id}><p className="eyebrow">{poll.status === "open" ? "Live poll" : "Poll results"}</p><strong>{poll.question}</strong>{poll.options.map(option => <button key={option.id} className="poll-option" disabled={busy || poll.status === "closed" || answeredPolls.includes(poll.id)} onClick={() => answer(poll, option.id)}>{option.label}{poll.status === "closed" && <span>{option.responses ?? 0}</span>}</button>)}{answeredPolls.includes(poll.id) && poll.status === "open" && <p className="poll-answer">Your response was recorded.</p>}</article>)}</div>}
          {chatPolicy.paused?<p className="chat-paused">Chat is temporarily paused by the host.</p>:<form className="chat-form" onSubmit={send}><label>Your name<input value={name} onChange={event => setName(event.target.value)} required /></label><label>Message<input value={content} onChange={event => setContent(event.target.value)} required /></label>{chatPolicy.slow_mode_seconds>0&&<small>Slow mode: one message every {chatPolicy.slow_mode_seconds} seconds.</small>}<button className="button button-primary" disabled={busy}>Send message</button>{error && <p className="form-error">{error}</p>}</form>}
        </> : <p className="chat-empty">Chat will open when the service is live.</p>}
      </aside>
    </div>
  </main>;
}
