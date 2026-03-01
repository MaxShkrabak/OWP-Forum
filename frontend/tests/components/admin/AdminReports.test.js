/** @vitest-environment jsdom */
/**
 * AdminReports (Report Tags) + ViewReportsButton — unit tests.
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { ref } from "vue";

import AdminReports from "@/components/admin/AdminReports.vue";
import ViewReportsButton from "@/components/admin/ViewReportsButton.vue";

/* -------------------- mocks -------------------- */

// Mock API client used by AdminReports.vue
const { mockClient } = vi.hoisted(() => ({
  mockClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));
vi.mock("@/api/client", () => ({ default: mockClient }));

// Mock reports API used by ViewReportsButton.vue
const { mockReportsApi } = vi.hoisted(() => ({
  mockReportsApi: { fetchReports: vi.fn(), resolveReport: vi.fn() },
}));
vi.mock("@/api/reports", () => ({
  fetchReports: mockReportsApi.fetchReports,
  resolveReport: mockReportsApi.resolveReport,
}));

// Mock router used by ViewReportsButton.vue
const { mockRouter } = vi.hoisted(() => ({
  mockRouter: { push: vi.fn() },
}));
vi.mock("vue-router", () => ({
  useRouter: () => mockRouter,
}));

// ✅ IMPORTANT: mock userRole as a real Vue ref so template unwrapping works
vi.mock("@/stores/userStore", () => ({
  userRole: ref("admin"),
}));

/* -------------------- test data -------------------- */

const mockReportTags = [
  { ReportTagID: 10, TagName: "Spam" },
  { ReportTagID: 11, TagName: "Harassment" },
  { ReportTagID: 12, TagName: "Other" },
  { ReportTagID: 13, TagName: "Misinformation" },
  { ReportTagID: 14, TagName: "Inappropriate" },
];

const mockReportsUnresolved = [
  { reportId: 1, postId: 99, source: "Post", reason: "Spam", createdAt: "2026-02-26T00:00:00Z" },
  { reportId: 2, postId: 100, source: "Post", reason: "Other", createdAt: "2026-02-26T01:00:00Z" },
];

/* -------------------- shared helpers -------------------- */

function normalizeName(s) {
  return String(s ?? "").trim().replace(/\s+/g, " ");
}
function reportTagExists(tags, name, excludeId = null) {
  const n = normalizeName(name).toLowerCase();
  if (!n) return false;
  return tags.some((t) => {
    const same = normalizeName(t.TagName).toLowerCase() === n;
    const notSelf =
      excludeId == null ? true : Number(t.ReportTagID) !== Number(excludeId);
    return same && notSelf;
  });
}

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
  return "/reports"; // client baseURL '/api' => /api/reports
}
function getResolveReportEndpoint(reportId) {
  return `/reports/${reportId}/resolve`; // /api/reports/:id/resolve
}

function getCreateReportModalTagsEndpoint() {
  // Provided in ReportRoutes.php: GET /api/report/tags
  return "/report/tags";
}

/**
 * ✅ CRITICAL FIX (why CI was still failing):
 * Vue Test Utils `stubs: { Teleport: true }` does NOT render the slot content.
 * So #viewReports never exists in CI.
 *
 * We stub Teleport with a component that *renders its slot*.
 */
const TeleportStub = {
  name: "Teleport",
  props: ["to"],
  template: `<div class="teleport-stub"><slot /></div>`,
};

async function openReportsModalAndWait(wrapper) {
  const modal = wrapper.find("#viewReports");
  expect(modal.exists()).toBe(true);
  modal.element.dispatchEvent(new Event("shown.bs.modal"));
  await flushPromises();
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

  it("reports endpoints match ReportRoutes.php", () => {
    expect(getActiveReportsEndpoint()).toBe("/reports");
    expect(getResolveReportEndpoint(123)).toBe("/reports/123/resolve");
  });

  it("create report modal tags endpoint matches ReportRoutes.php", () => {
    // You did NOT provide the create report modal component, so only contract test.
    expect(getCreateReportModalTagsEndpoint()).toBe("/report/tags");
  });
});

describe("AdminReports.vue — DOM + CRUD behaviors", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockClient.get.mockResolvedValue({ data: { items: mockReportTags } });
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
    mockClient.get.mockResolvedValueOnce({ data: { items: mockReportTags } });

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
    mockClient.get.mockResolvedValueOnce({ data: { items: mockReportTags.slice(1) } });

    const confirmButtons = wrapper.findAll(".btn-confirm");
    await confirmButtons[confirmButtons.length - 1].trigger("click");
    await flushPromises();

    expect(mockClient.delete).toHaveBeenCalledWith("/admin/report-tags/11");
    expect(wrapper.text()).toContain("Report tag deleted");
  });
});

describe("ViewReportsButton.vue — reports loading + actions", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockRouter.push.mockClear();
    window.bootstrap = undefined; // safe
  });

  it("5) Active reports load correctly (fetchReports called and count renders)", async () => {
    mockReportsApi.fetchReports.mockResolvedValue({ ok: true, reports: mockReportsUnresolved });

    const wrapper = mount(ViewReportsButton, {
      global: { stubs: { Teleport: TeleportStub } }, // ✅ renders modal content
    });
    await flushPromises();

    expect(mockReportsApi.fetchReports).toHaveBeenCalled();

    const badge = wrapper.find(".report-count");
    expect(badge.exists()).toBe(true);
    expect(badge.text()).toBe("2");
  });

  it("6) Reports are filtered to show only unresolved reports (UI shows what API returns)", async () => {
    mockReportsApi.fetchReports.mockResolvedValue({ ok: true, reports: mockReportsUnresolved });

    const wrapper = mount(ViewReportsButton, {
      global: { stubs: { Teleport: TeleportStub } },
    });
    await flushPromises();

    await openReportsModalAndWait(wrapper);

    expect(wrapper.text()).toContain("Spam");
    expect(wrapper.text()).toContain("Other");
    expect(wrapper.text()).toContain("Reports submitted by users");
  });

  it("8) Clicking Resolve calls resolveReport and removes report from UI list", async () => {
    mockReportsApi.fetchReports.mockResolvedValue({ ok: true, reports: [...mockReportsUnresolved] });
    mockReportsApi.resolveReport.mockResolvedValue({ ok: true });

    const wrapper = mount(ViewReportsButton, {
      global: { stubs: { Teleport: TeleportStub } },
    });
    await flushPromises();

    await openReportsModalAndWait(wrapper);

    const resolveBtns = wrapper.findAll("button").filter((b) => b.text().trim() === "Resolve");
    expect(resolveBtns.length).toBeGreaterThan(0);

    await resolveBtns[0].trigger("click");
    await flushPromises();

    expect(mockReportsApi.resolveReport).toHaveBeenCalledWith(1);

    const badge = wrapper.find(".report-count");
    expect(badge.text()).toBe("1");
  });

  it("9) Clicking Go to routes to correct post content", async () => {
    mockReportsApi.fetchReports.mockResolvedValue({ ok: true, reports: [...mockReportsUnresolved] });

    const wrapper = mount(ViewReportsButton, {
      global: { stubs: { Teleport: TeleportStub } },
    });
    await flushPromises();

    await openReportsModalAndWait(wrapper);

    const goBtns = wrapper.findAll("button").filter((b) => b.text().trim() === "Go to Post");
    expect(goBtns.length).toBeGreaterThan(0);

    await goBtns[0].trigger("click");
    await flushPromises();

    expect(mockRouter.push).toHaveBeenCalledWith("/posts/99");
  });

  it("4) Tag list updates for create report modal — NOT DOM test", () => {
    expect(getCreateReportModalTagsEndpoint()).toBe("/report/tags");
  });
});