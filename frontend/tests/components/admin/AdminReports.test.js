/** @vitest-environment jsdom */
/**
 * AdminReports — unit tests.
 * Includes: Report Tags + Manage Reports section (tests 1–3, 5–9).
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { ref } from "vue";

// tests/components/admin/AdminReports.test.js -> src/components/admin/*.vue
import AdminReports from "../../../src/components/admin/AdminReports.vue";

/* -------------------- mocks -------------------- */

// Mock API client used by AdminReports.vue
const { mockClient } = vi.hoisted(() => ({
  mockClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

// Virtual mock so "@/api/client" doesn't need to resolve to a real path
vi.mock("@/api/client", () => ({ default: mockClient }), { virtual: true });

// Mock reports API used by AdminReports.vue (resolveReport)
const { mockReportsApi } = vi.hoisted(() => ({
  mockReportsApi: { resolveReport: vi.fn() },
}));

// Virtual mock so "@/api/reports" doesn't need alias resolution
vi.mock(
  "@/api/reports",
  () => ({
    resolveReport: mockReportsApi.resolveReport,
  }),
  { virtual: true }
);

// Mock router used by AdminReports.vue
const { mockRouter } = vi.hoisted(() => ({
  mockRouter: { push: vi.fn() },
}));
vi.mock("vue-router", () => ({
  useRouter: () => mockRouter,
}));

// IMPORTANT: mock userRole as a real Vue ref so template unwrapping works
// Virtual mock so "@/stores/userStore" doesn't need alias resolution
vi.mock(
  "@/stores/userStore",
  () => ({
    userRole: ref("admin"),
  }),
  { virtual: true }
);

/* -------------------- test data -------------------- */

const mockReportTags = [
  { ReportTagID: 10, TagName: "Spam" },
  { ReportTagID: 11, TagName: "Harassment" },
  { ReportTagID: 12, TagName: "Other" },
  { ReportTagID: 13, TagName: "Misinformation" },
  { ReportTagID: 14, TagName: "Inappropriate" },
];

const mockAdminReports = [
  {
    reportId: 1,
    postId: 99,
    source: "Post",
    reason: "Spam",
    createdAt: "2026-02-26T00:00:00Z",
    contentTitle: "Post 99 Title",
  },
  {
    reportId: 2,
    postId: 100,
    source: "Post",
    reason: "Other",
    createdAt: "2026-02-26T01:00:00Z",
    contentTitle: "Post 100 Title",
  },
];

/* -------------------- shared helpers (tags) -------------------- */

function normalizeName(s) {
  return String(s ?? "").trim().replace(/\s+/g, " ");
}
function reportTagExists(tags, name, excludeId = null) {
  const n = normalizeName(name).toLowerCase();
  if (!n) return false;
  return tags.some((t) => {
    const same = normalizeName(t.TagName).toLowerCase() === n;
    const notSelf = excludeId == null ? true : Number(t.ReportTagID) !== Number(excludeId);
    return same && notSelf;
  });
}

/* -------------------- endpoint contract helpers -------------------- */

function getReportTagsListEndpoint() {
  return "/admin/report-tags";
}
function getReportTagsAddEndpoint() {
  return "/admin/report-tags";
}
function getReportTagsEditEndpoint(id) {
  return `/admin/report-tags/${id}`;
}
function getReportTagsDeleteEndpoint(id) {
  return `/admin/report-tags/${id}`;
}

function getActiveReportsEndpoint() {
  return "/admin/reports"; // client baseURL '/api' => /api/admin/reports
}

function getCreateReportModalTagsEndpoint() {
  // Provided in ReportRoutes.php: GET /api/report/tags
  return "/report/tags";
}

/* -------------------- tests -------------------- */

describe("Report Tags (Admin) — duplicate prevention", () => {
  it("detects duplicates (case-insensitive, normalized)", () => {
    const tags = [
      { ReportTagID: 1, TagName: "Spam" },
      { ReportTagID: 2, TagName: "Inappropriate" },
    ];
    expect(reportTagExists(tags, "spam")).toBe(true);
    expect(reportTagExists(tags, "  Inappropriate ")).toBe(true);
    expect(reportTagExists(tags, "Other")).toBe(false);
  });

  it("allows editing same record (excludeId)", () => {
    const tags = [
      { ReportTagID: 1, TagName: "Spam" },
      { ReportTagID: 2, TagName: "Other" },
    ];
    expect(reportTagExists(tags, "Spam", 1)).toBe(false);
    expect(reportTagExists(tags, "Other", 2)).toBe(false);
    expect(reportTagExists(tags, "Other", 1)).toBe(true);
  });
});

describe("Report Tags (Admin) — API contract", () => {
  it("list/add/edit/delete endpoints are correct", () => {
    expect(getReportTagsListEndpoint()).toBe("/admin/report-tags");
    expect(getReportTagsAddEndpoint()).toBe("/admin/report-tags");
    expect(getReportTagsEditEndpoint(7)).toBe("/admin/report-tags/7");
    expect(getReportTagsDeleteEndpoint(7)).toBe("/admin/report-tags/7");
  });

  it("reports endpoint matches AdminReports.vue", () => {
    expect(getActiveReportsEndpoint()).toBe("/admin/reports");
  });

  it("create report modal tags endpoint matches ReportRoutes.php", () => {
    expect(getCreateReportModalTagsEndpoint()).toBe("/report/tags");
  });
});

describe("AdminReports.vue — DOM + CRUD behaviors", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockRouter.push.mockClear();

    // AdminReports calls:
    //  - GET /admin/report-tags on mount
    //  - GET /admin/reports on mount
    //  - optional GET /get-post/:id for enrichment
    mockClient.get.mockImplementation((url) => {
      if (url === "/admin/report-tags") return Promise.resolve({ data: { items: mockReportTags } });

      if (url === "/admin/reports")
        return Promise.resolve({ data: { ok: true, reports: mockAdminReports } });

      // enrichment calls (harmless defaults)
      if (String(url).startsWith("/get-post/")) {
        return Promise.resolve({
          data: { ok: true, post: { title: "Enriched", authorId: 1, authorName: "Author" } },
        });
      }

      return Promise.resolve({ data: {} });
    });
  });

  it("1) All existing report tags load correctly", async () => {
    const wrapper = mount(AdminReports);
    await flushPromises();

    expect(mockClient.get).toHaveBeenCalledWith("/admin/report-tags");
    expect(wrapper.find(".admin-table").exists()).toBe(true);
    expect(wrapper.text()).toContain("Spam");
    expect(wrapper.text()).toContain("Harassment");
    expect(wrapper.text()).toContain("Inappropriate");
  });

  it("7) Sorting works correctly (alphabetical by TagName)", async () => {
    const wrapper = mount(AdminReports);
    await flushPromises();

    const cells = wrapper.findAll("tbody td.admin-name");
    const names = cells.map((c) => c.text().trim());
    expect(names).toEqual(["Harassment", "Inappropriate", "Misinformation", "Other", "Spam"]);
  });

  it("2) Editing a tag triggers PATCH and refreshes list", async () => {
    const wrapper = mount(AdminReports);
    await flushPromises();

    const editBtns = wrapper.findAll(".btn-action");
    await editBtns[0].trigger("click");

    const input = wrapper.find("input.field-input");
    await input.setValue("Harassment Updated");

    mockClient.patch.mockResolvedValue({ data: { ok: true } });

    // First click opens confirm modal
    await wrapper.find(".btn-confirm").trigger("click");
    expect(wrapper.text()).toContain("Confirm edit report tag?");

    // Confirm
    const confirmButtons = wrapper.findAll(".btn-confirm");
    await confirmButtons[confirmButtons.length - 1].trigger("click");
    await flushPromises();

    expect(mockClient.patch).toHaveBeenCalledWith("/admin/report-tags/11", {
      tagName: "Harassment Updated",
    });
    expect(mockClient.get).toHaveBeenCalledWith("/admin/report-tags");
  });

  it("3) Deleting a tag calls DELETE and shows success message", async () => {
    const wrapper = mount(AdminReports);
    await flushPromises();

    const deleteBtns = wrapper.findAll(".btn-action.danger");
    await deleteBtns[0].trigger("click");
    expect(wrapper.text()).toContain("Confirm delete report tag?");

    mockClient.delete.mockResolvedValue({ data: { ok: true } });

    const confirmButtons = wrapper.findAll(".btn-confirm");
    await confirmButtons[confirmButtons.length - 1].trigger("click");
    await flushPromises();

    expect(mockClient.delete).toHaveBeenCalledWith("/admin/report-tags/11");
    expect(wrapper.text()).toContain("Report tag deleted");
  });

  it("5) Active reports load correctly (GET /admin/reports called and list renders)", async () => {
    const wrapper = mount(AdminReports);
    await flushPromises();

    expect(mockClient.get).toHaveBeenCalledWith("/admin/reports");

    const rows = wrapper.findAll(".reports-list .report-row");
    expect(rows.length).toBe(2);

    expect(wrapper.text()).toContain("Post 99 Title");
    expect(wrapper.text()).toContain("Post 100 Title");
  });

  it("6) Reports are filtered to show only unresolved reports (UI shows what API returns)", async () => {
    // AdminReports.vue itself does not filter by status;
    // It shows whatever /admin/reports returns.
    mockClient.get.mockImplementation((url) => {
      if (url === "/admin/report-tags") return Promise.resolve({ data: { items: mockReportTags } });
      if (url === "/admin/reports")
        return Promise.resolve({ data: { ok: true, reports: mockAdminReports } });
      return Promise.resolve({ data: {} });
    });

    const wrapper = mount(AdminReports);
    await flushPromises();

    const rows = wrapper.findAll(".reports-list .report-row");
    expect(rows.length).toBe(2);

    expect(wrapper.text()).toContain("Spam");
    expect(wrapper.text()).toContain("Other");
    expect(wrapper.text()).not.toContain("No active reports (unresolved).");
  });

 it("8) Clicking Resolve calls resolveReport and removes report from UI list", async () => {
  mockReportsApi.resolveReport.mockResolvedValue({ ok: true });

  const wrapper = mount(AdminReports);
  await flushPromises();

  // Find the row that corresponds to Post #99 (order can be newest-first)
  const rows = wrapper.findAll(".reports-list .report-row");
  const targetRow = rows.find((row) => row.text().includes("Post #99"));
  expect(targetRow, "Expected a report row for Post #99").toBeTruthy();

  await targetRow.find("button.btn-solid").trigger("click"); // Resolve
  await flushPromises();

  expect(mockReportsApi.resolveReport).toHaveBeenCalledWith(1);

  // Should remove that report from UI
  const remainingRows = wrapper.findAll(".reports-list .report-row");
  expect(remainingRows.length).toBe(1);
  expect(wrapper.text()).not.toContain("Post #99");
  expect(wrapper.text()).toContain("Post #100");
});

it('9) Clicking "Go to" routes to correct report content', async () => {
  const wrapper = mount(AdminReports);
  await flushPromises();

  // Find the row that corresponds to Post #99
  const rows = wrapper.findAll(".reports-list .report-row");
  const targetRow = rows.find((row) => row.text().includes("Post #99"));
  expect(targetRow, "Expected a report row for Post #99").toBeTruthy();

  await targetRow.find("button.btn-outline").trigger("click"); // Go to
  await flushPromises();

  expect(mockRouter.push).toHaveBeenCalledWith("/posts/99");
});
});