"use client";

import { useParams } from "next/navigation";
import WorkspacePage from "../WorkspacePage";

export default function AdminAreaPage() { const params = useParams<{ area: string }>(); return <WorkspacePage area={params.area} />; }
