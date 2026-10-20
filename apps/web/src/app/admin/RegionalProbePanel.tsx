"use client";

import { useEffect, useState } from "react";
import { api } from "./api";

type ProbeResult={id:string;region_key:string;region_label:string;probe_type:"local"|"remote";status:"healthy"|"offline";http_status:number|null;latency_ms:number;error_code:string|null};
type ProbeRun={id:string;trigger:"manual"|"scheduled";status:"healthy"|"degraded"|"offline";created_at:string;results:ProbeResult[]};
type ProbeData={regions:Array<{key:string;label:string;type:"local"|"remote"}>;provider_capabilities:Record<string,boolean>|null;runs:ProbeRun[]};

export default function RegionalProbePanel({serviceId,configured}:{serviceId:string;configured:boolean}){
 const [data,setData]=useState<ProbeData|null>(null);const [busy,setBusy]=useState(false);const [error,setError]=useState("");
 async function load(){if(!serviceId){setData(null);return}const response=await api<{data:ProbeData}>(`/staff/services/${serviceId}/stream/regional-probes`);setData(response.data)}
 useEffect(()=>{void load().catch(c=>setError(c instanceof Error?c.message:"Could not load regional probes."))},[serviceId]);
 async function run(){setBusy(true);setError("");try{await api(`/staff/services/${serviceId}/stream/regional-probes`,{method:"POST"});await load()}catch(c){setError(c instanceof Error?c.message:"Regional probes could not run.")}finally{setBusy(false)}}
 const latest=data?.runs[0];
 return <section className="regional-probes"><div className="stream-heading"><div><p className="eyebrow">Distribution checks</p><h3>Regional playback probes</h3></div>{latest&&<span className={`stream-health ${latest.status}`}>{latest.status}</span>}</div><div className="regional-probe-toolbar"><p>{data?.regions.map(region=>region.label).join(" · ")||"Installation origin"}</p><button className="workspace-secondary-button" onClick={()=>void run()} disabled={busy||!configured}>{busy?"Probing…":"Run all probes"}</button></div>{data?.provider_capabilities&&<div className="provider-capabilities"><strong>Provider capabilities</strong>{Object.entries(data.provider_capabilities).map(([name,enabled])=><span className={enabled?"available":"unavailable"} key={name}>{enabled?"✓":"—"} {name.replaceAll("_"," ")}</span>)}</div>}<div className="probe-run-list">{data?.runs.map(run=><article key={run.id}><header><strong>{run.trigger} probe</strong><span>{new Date(run.created_at).toLocaleString()}</span></header><div>{run.results.map(result=><p key={result.id}><b className={result.status}>{result.status==="healthy"?"●":"×"}</b><span>{result.region_label}<small>{result.probe_type} · HTTP {result.http_status??"none"}</small></span><strong>{result.latency_ms} ms</strong></p>)}</div></article>)}</div>{configured&&data?.runs.length===0&&<p className="diagnostic-definition">No regional probe run has been recorded for this stream.</p>}{!configured&&<p className="diagnostic-definition">Connect a playback source before running distribution checks.</p>}{error&&<p className="form-error" role="alert">{error}</p>}</section>
}
