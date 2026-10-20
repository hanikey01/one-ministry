import WorshipRoom from "./WorshipRoom";

export default async function WorshipPage({ params }: { params: Promise<{ serviceId: string }> }) {
  const { serviceId } = await params;
  return <WorshipRoom serviceId={serviceId} />;
}
