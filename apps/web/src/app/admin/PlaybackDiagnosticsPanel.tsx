"use client";

import { useEffect, useState } from "react";
import { api } from "./api";

type Summary={window_hours:number;active_recently:number;viewer_sessions:number;starts:number;stalls:number;recoveries:number;failures:number;average_startup_ms:number;quality_events:Array<{quality:string;events:number}>;definition:string};

export default function PlaybackDiagnosticsPanel({serviceId}:{serviceId:string}){
 const[data,setData]=useState<Summary|null>(null),[error,setError]=useState("");
 useEffect(()=>{if(!serviceId){setData(null);return}let active=true;const load=()=>api<{data:Summary}>(`/staff/services/${serviceId}/playback-diagnostics`).then(r=>{if(active){setData(r.data);setError("")}}).catch(c=>{if(active)setError(c instanceof Error?c.message:"Could not load playback diagnostics.")});void load();const timer=window.setInterval(()=>void load(),15000);return()=>{active=false;window.clearInterval(timer)}},[serviceId]);
 if(!serviceId)return null;
 const metrics=[["Active recently",data?.active_recently??0,"Sessions with activity in the past 45 seconds"],["Viewer sessions",data?.viewer_sessions??0,`Anonymous device sessions in ${data?.window_hours??6} hours`],["Playback starts",data?.starts??0,"Native HLS starts reported"],["Stalls",data?.stalls??0,"Buffering events"],["Failures",data?.failures??0,"Player failures"],["Average startup",`${data?.average_startup_ms??0} ms`,"Reported player startup time"]] as const;
 return <section className="playback-diagnostics"><div className="stream-heading"><div><p className="eyebrow">Viewer experience</p><h3>Playback diagnostics</h3></div><span>Refreshes every 15 seconds</span></div><div className="diagnostic-metrics">{metrics.map(([label,value,help])=><article key={label}><span>{label}</span><strong>{value}</strong><small>{help}</small></article>)}</div><p className="diagnostic-definition">{data?.definition||"Waiting for anonymous playback telemetry."}</p>{error&&<p className="form-error">{error}</p>}</section>
}
