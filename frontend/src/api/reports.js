import client from "./client";

export async function fetchReports() {
  const { data } = await client.get("/reports");
  return data;
}

export async function resolveReport(reportId) {
  const { data } = await client.patch(`/reports/${reportId}/resolve`);
  return data;
}
