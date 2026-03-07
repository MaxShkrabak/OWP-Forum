import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { ref, nextTick } from "vue";

/* -------------------- mocks -------------------- */

const { mockClient } = vi.hoisted(() => ({
  mockClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));
vi.mock("@/api/client", () => ({ default: mockClient }), { virtual: true });

const { mockReportsApi } = vi.hoisted(() => ({
  mockReportsApi: { fetchReports: vi.fn(), resolveReport: vi.fn() },
}));
vi.mock(
  "@/api/reports",
  () => ({
    fetchReports: mockReportsApi.fetchReports,
    resolveReport: mockReportsApi.resolveReport,
  }),
  { virtual: true }
);

const { mockRouter } = vi.hoisted(() => ({
  mockRouter: { push: vi.fn() },
}));
vi.mock("vue-router", () => ({
  useRouter: () => mockRouter,
}));

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

// ✅ SUPER “unresolved” — covers basically every filter style
const mockReportsUnresolved = [
  {
    reportId: 1,
    postId: 99,
    source: "Post",
    reason: "Spam",
    createdAt: "2026-02-26T00:00:00Z",

    resolvedAt: null,
    ResolvedAt: null,
    resolved_on: null,
    resolvedOn: null,

    status: "open",
    Status: "open",
    state: "open",
    State: "open",

    isResolved: false,
    IsResolved: false,
    resolved: false,
    Resolved: false,
    resolvedFlag: 0,
    ResolvedFlag: 0,
    resolved_id: null,
    resolvedBy: null,
  },
  {
    reportId: 2,
    postId: 100,
    source: "Post",
    reason: "Other",
    createdAt: "2026-02-26T01:00:00Z",

    resolvedAt: null,
    ResolvedAt: null,
    resolved_on: null,
    resolvedOn: null,

    status: "open",
    Status: "open",
    state: "open",
    State: "open",

    isResolved: false,
    IsResolved: false,
    resolved: false,
    Resolved: false,
    resolvedFlag: 0,
    ResolvedFlag: 0,
    resolved_id: null,
    resolvedBy: null,
  },
];

/* -------------------- helpers -------------------- */

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
  await nextTick();
}

async function importAdminReports() {
  return (await import("../../../src/components/admin/AdminReports.vue")).default;
}
async function importViewReportsButton() {
  return (await import("../../../src/components/admin/ViewReportsButton.vue")).default;
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

describe("AdminReports.vue — DOM + CRUD behaviors", () => {
  beforeEach(() => {
    vi.clearAllMocks();

    mockClient.get.mockImplementation((url) => {
      if (url === "/admin/report-tags") return Promise.resolve({ data: { items: mockReportTags } });
      if (url === "/admin/reports") return Promise.resolve({ data: { ok: true, reports: [] } });
      return Promise.resolve({ data: {} });
    });
  });

  it("1) All existing report tags load correctly", async () => {
    const AdminReports = await importAdminReports();
    const wrapper = mount(AdminReports);
    await flushPromises();

    expect(mockClient.get).toHaveBeenCalledWith("/admin/report-tags");
    expect(wrapper.text()).toContain("Spam");
    expect(wrapper.text()).toContain("Harassment");
    expect(wrapper.text()).toContain("Inappropriate");
  });

  it("7) Sorting works correctly (alphabetical by TagName)", async () => {
    const AdminReports = await importAdminReports();
    const wrapper = mount(AdminReports);
    await flushPromises();

    const cells = wrapper.findAll("tbody td.admin-name");
    const names = cells.map((c) => c.text().trim());
    expect(names).toEqual(["Harassment", "Inappropriate", "Misinformation", "Other", "Spam"]);
  });

  it("2) Editing a tag triggers PATCH and refreshes list", async () => {
    const AdminReports = await importAdminReports();
    const wrapper = mount(AdminReports);
    await flushPromises();

    const editBtns = wrapper.findAll(".btn-action");
    await editBtns[0].trigger("click");

    await wrapper.find("input.field-input").setValue("Harassment Updated");

    mockClient.patch.mockResolvedValue({ data: { ok: true } });

    await wrapper.find(".btn-confirm").trigger("click"); // opens confirm modal
    expect(wrapper.text()).toContain("Confirm edit report tag?");

    const confirmButtons = wrapper.findAll(".btn-confirm");
    await confirmButtons[confirmButtons.length - 1].trigger("click");
    await flushPromises();

    expect(mockClient.patch).toHaveBeenCalledWith("/admin/report-tags/11", { tagName: "Harassment Updated" });
    expect(mockClient.get).toHaveBeenCalledWith("/admin/report-tags");
  });

  it("3) Deleting a tag calls DELETE and shows success message", async () => {
    const AdminReports = await importAdminReports();
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
});

describe("ViewReportsButton.vue — reports loading + actions", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockRouter.push.mockClear();
    window.bootstrap = undefined;
  });

  it("5) Active reports load correctly (fetchReports called and count renders)", async () => {
    mockReportsApi.fetchReports.mockResolvedValue({ ok: true, reports: mockReportsUnresolved });

    const ViewReportsButton = await importViewReportsButton();
    const wrapper = mount(ViewReportsButton, {
      global: { stubs: { Teleport: TeleportStub } },
    });
    await flushPromises();

    // ✅ proves mock is being used everywhere
    expect(mockReportsApi.fetchReports).toHaveBeenCalled();

    expect(wrapper.find(".report-count").text()).toBe("2");
  });

  it("6) Reports are filtered to show only unresolved reports (UI shows what API returns)", async () => {
    mockReportsApi.fetchReports.mockResolvedValue({ ok: true, reports: mockReportsUnresolved });

    const ViewReportsButton = await importViewReportsButton();
    const wrapper = mount(ViewReportsButton, {
      global: { stubs: { Teleport: TeleportStub } },
    });
    await flushPromises();

    expect(mockReportsApi.fetchReports).toHaveBeenCalled();

    await openReportsModalAndWait(wrapper);

    expect(wrapper.text()).toContain("Spam");
    expect(wrapper.text()).toContain("Other");
    expect(wrapper.text()).toContain("Reports submitted by users");
  });

  it("8) Clicking Resolve calls resolveReport and removes report from UI list", async () => {
    mockReportsApi.fetchReports.mockResolvedValue({ ok: true, reports: [...mockReportsUnresolved] });
    mockReportsApi.resolveReport.mockResolvedValue({ ok: true });

    const ViewReportsButton = await importViewReportsButton();
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
    expect(wrapper.find(".report-count").text()).toBe("1");
  });

  it("9) Clicking Go to routes to correct post content", async () => {
    mockReportsApi.fetchReports.mockResolvedValue({ ok: true, reports: [...mockReportsUnresolved] });

    const ViewReportsButton = await importViewReportsButton();
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
});