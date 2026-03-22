import client from "./client";

export async function fetchReports() {
  const { data } = await client.get("/reports");
  return data;
}

export async function resolveReport(reportId) {
  const { data } = await client.patch(`/reports/${reportId}/resolve`);
  return data;
}

// Fetch report tags for report modal
export async function getReportTags() {
  const { data } = await client.get("/report/tags");
  return (data.tags || []).map((reportTag) => ({
    tagID: Number(reportTag.ReportTagID),
    name: reportTag.TagName,
  }));
}

// Process report
export async function submitReport(reportData) {
    try {
      const { data } = await client.post("/report", reportData);
      return data;
    } catch (error) {
        if (error.response && error.response.data) {
            return {
                ok: false,
                error: error.response.data.error || "Failed to submit report"
            };
        }
    }
}
