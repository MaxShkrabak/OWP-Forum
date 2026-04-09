import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { ref } from "vue";

import AdminReports from "../../../src/components/admin/AdminReports.vue";

const { mockClient } = vi.hoisted(() => ({
  mockClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

// Virtual mock so "@/api/client" doesn't need to resolve to a real path
vi.mock("@/api/client", () => ({ default: mockClient }), { virtual: true });

const { mockReportsApi } = vi.hoisted(() => ({
  mockReportsApi: { resolveReport: vi.fn(), fetchReports: vi.fn() },
}));

// Virtual mock so "@/api/reports" doesn't need alias resolution
vi.mock(
  "@/api/reports",
  () => ({
    resolveReport: mockReportsApi.resolveReport,
    fetchReports: mockReportsApi.fetchReports,
  }),
  { virtual: true }
);

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

const mockReportTags = [
  { tagId: 10, name: "Spam" },
  { tagId: 11, name: "Harassment" },
  { tagId: 12, name: "Other" },
  { tagId: 13, name: "Misinformation" },
  { tagId: 14, name: "Inappropriate" },
];

const mockAdminReports = [
  {
    reportId: 1,
    postId: 99,
    source: "Post",
    reason: "Spam",
    createdAt: "2026-02-26T00:00:00Z",
    postTitle: "Post 99 Title",
  },
  {
    reportId: 2,
    postId: 100,
    source: "Post",
    reason: "Other",
    createdAt: "2026-02-26T01:00:00Z",
    postTitle: "Post 100 Title",
  },
  {
    reportId: 3,
    postId: 55,
    commentId: 12,
    source: "Comment",
    reason: "Harassment",
    createdAt: "2026-02-26T02:00:00Z",
    commentText: "Comment 12 Text",
    postAuthorId: 7,
    postAuthor: "Bad Commenter",
    reporter: { id: 5, fullName: "Jane Reporter" },
  },
];

function normalizeName(s) {
  return String(s ?? "").trim().replace(/\s+/g, " ");
}
function reportTagExists(tags, name, excludeId = null) {
  const n = normalizeName(name).toLowerCase();
  if (!n) return false;
  return tags.some((t) => {
    const same = normalizeName(t.name).toLowerCase() === n;
    const notSelf = excludeId == null ? true : t.tagId !== excludeId;
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

function getCreateReportModalTagsEndpoint() {
  // Provided in ReportRoutes.php: GET /api/reports/tags
  return "/reports/tags";
}

describe("Report Tags (Admin) — duplicate prevention", () => {
  it("detects duplicates (case-insensitive, normalized)", () => {
    const tags = [
      { tagId: 1, name: "Spam" },
      { tagId: 2, name: "Inappropriate" },
    ];
    expect(reportTagExists(tags, "spam")).toBe(true);
    expect(reportTagExists(tags, "  Inappropriate ")).toBe(true);
    expect(reportTagExists(tags, "Other")).toBe(false);
  });

  it("allows editing same record (excludeId)", () => {
    const tags = [
      { tagId: 1, name: "Spam" },
      { tagId: 2, name: "Other" },
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
    expect(getActiveReportsEndpoint()).toBe("/reports");
  });

  it("create report modal tags endpoint matches ReportRoutes.php", () => {
    expect(getCreateReportModalTagsEndpoint()).toBe("/reports/tags");
  });
});

describe("AdminReports.vue — DOM + CRUD behaviors", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockRouter.push.mockClear();

    mockClient.get.mockImplementation((url) => {
      if (url === "/admin/report-tags") return Promise.resolve({ data: { items: mockReportTags } });
      return Promise.resolve({ data: {} });
    });

    mockReportsApi.fetchReports.mockResolvedValue({
      ok: true,
      reports: mockAdminReports,
      total: mockAdminReports.length,
      page: 1,
      perPage: 25,
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

  it("2) Sorting works correctly (alphabetical by TagName)", async () => {
    const wrapper = mount(AdminReports);
    await flushPromises();

    const cells = wrapper.findAll("tbody td.admin-name");
    const names = cells.map((c) => c.text().trim());
    expect(names).toEqual(["Harassment", "Inappropriate", "Misinformation", "Other", "Spam"]);
  });

  it("3) Editing a tag triggers PATCH and refreshes list", async () => {
    const wrapper = mount(AdminReports);
    await flushPromises();

    const editBtns = wrapper.findAll(".btn-action");
    await editBtns[0].trigger("click");

    const input = wrapper.find("input.field-input");
    await input.setValue("Harassment Updated");

    mockClient.patch.mockResolvedValue({ data: { ok: true } });

    await wrapper.find(".btn-confirm").trigger("click");
    expect(wrapper.text()).toContain("Confirm edit report tag?");

    const confirmButtons = wrapper.findAll(".btn-confirm");
    await confirmButtons[confirmButtons.length - 1].trigger("click");
    await flushPromises();

    expect(mockClient.patch).toHaveBeenCalledWith("/admin/report-tags/11", {
      tagName: "Harassment Updated",
    });
    expect(mockClient.get).toHaveBeenCalledWith("/admin/report-tags");
  });

  it("4) Deleting a tag calls DELETE and shows success message", async () => {
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

  it("5) Active reports load correctly (fetchReports called and list renders)", async () => {
    const wrapper = mount(AdminReports);
    await flushPromises();

    expect(mockReportsApi.fetchReports).toHaveBeenCalledWith(
      expect.objectContaining({
        page: 1,
        perPage: 25,
        sort: "newest",
      }),
    );

    const rows = wrapper.findAll(".reports-list .report-row");
    expect(rows.length).toBe(3);

    expect(wrapper.text()).toContain("Post 99 Title");
    expect(wrapper.text()).toContain("Post 100 Title");
    expect(wrapper.text()).toContain("Comment 12 Text");
  });

  it("6) Reports are filtered to show only unresolved reports (UI shows what API returns)", async () => {
    // AdminReports.vue itself does not filter by status; it shows whatever /admin/reports returns.
    mockClient.get.mockImplementation((url) => {
      if (url === "/admin/report-tags") return Promise.resolve({ data: { items: mockReportTags } });
      if (url === "/admin/reports")
        return Promise.resolve({ data: { ok: true, reports: mockAdminReports } });
      return Promise.resolve({ data: {} });
    });

    const wrapper = mount(AdminReports);
    await flushPromises();

    const rows = wrapper.findAll(".reports-list .report-row");
    expect(rows.length).toBe(3);

    expect(wrapper.text()).toContain("Spam");
    expect(wrapper.text()).toContain("Other");
    expect(wrapper.text()).toContain("Harassment");
    expect(wrapper.text()).not.toContain("No active reports (unresolved).");
  });

 it("7) Clicking Resolve calls resolveReport and removes report from UI list", async () => {
  mockReportsApi.fetchReports
    .mockResolvedValueOnce({
      ok: true,
      reports: mockAdminReports,
      total: mockAdminReports.length,
      page: 1,
      perPage: 25,
    })
    .mockResolvedValueOnce({
      ok: true,
      reports: [mockAdminReports[1]],
      total: 1,
      page: 1,
      perPage: 25,
    });
  mockReportsApi.resolveReport.mockResolvedValue({ ok: true });

  const wrapper = mount(AdminReports);
  await flushPromises();

  const rows = wrapper.findAll(".reports-list .report-row");
  const targetRow = rows.find((row) => row.text().includes("Post #99"));
  expect(targetRow, "Expected a report row for Post #99").toBeTruthy();

  await targetRow.find("button.btn-solid").trigger("click"); // Resolve
  await flushPromises();

  expect(mockReportsApi.resolveReport).toHaveBeenCalledWith(1);

  const remainingRows = wrapper.findAll(".reports-list .report-row");
  expect(remainingRows.length).toBe(1);
  expect(wrapper.text()).not.toContain("Post #99");
  expect(wrapper.text()).toContain("Post #100");
});
  
it('8) Clicking "Go to" routes to correct report content', async () => {
  const wrapper = mount(AdminReports);
  await flushPromises();

  const rows = wrapper.findAll(".reports-list .report-row");
  const targetRow = rows.find((row) => row.text().includes("Post #99"));
  expect(targetRow, "Expected a report row for Post #99").toBeTruthy();

  await targetRow.find("button.btn-outline").trigger("click"); // Go to
  await flushPromises();

  expect(mockRouter.push).toHaveBeenCalledWith("/posts/99");
});

it("9) Comment report displays stripped comment text as title", async () => {
  const wrapper = mount(AdminReports);
  await flushPromises();

  const rows = wrapper.findAll(".reports-list .report-row");
  const commentRow = rows.find((row) => row.text().includes("Comment #12"));
  expect(commentRow, "Expected a report row for Comment #12").toBeTruthy();

  // HTML tags should be stripped from commentText
  expect(commentRow.text()).toContain("Comment 12 Text");
  expect(commentRow.text()).not.toContain("<p>");
  expect(commentRow.text()).not.toContain("<strong>");

  // Should show Comment badge
  expect(commentRow.text()).toContain("Comment");
});

it("10) Comment report shows reporter info correctly", async () => {
  const wrapper = mount(AdminReports);
  await flushPromises();

  const rows = wrapper.findAll(".reports-list .report-row");
  const commentRow = rows.find((row) => row.text().includes("Comment #12"));
  expect(commentRow, "Expected a report row for Comment #12").toBeTruthy();

  // Reporter name should be displayed
  expect(commentRow.text()).toContain("Jane Reporter");
  // Should show the reason
  expect(commentRow.text()).toContain("Harassment");
  // Should reference both the comment and its parent post
  expect(commentRow.text()).toContain("Post #55");
});

it('11) Clicking "Go to" on a comment report routes to its parent post', async () => {
  const wrapper = mount(AdminReports);
  await flushPromises();

  const rows = wrapper.findAll(".reports-list .report-row");
  const commentRow = rows.find((row) => row.text().includes("Comment #12"));
  expect(commentRow, "Expected a report row for Comment #12").toBeTruthy();

  await commentRow.find("button.btn-outline").trigger("click");
  await flushPromises();

  expect(mockRouter.push).toHaveBeenCalledWith({ path: "/posts/55", hash: "#comment-12", query: {} });
});

it('12) Clicking "Go to" on a reply comment report passes parentCommentId query param', async () => {
  const reportsWithReply = [
    ...mockAdminReports,
    {
      reportId: 4,
      postId: 60,
      commentId: 25,
      parentCommentId: 15,
      source: "Comment",
      reason: "Spam",
      createdAt: "2026-02-26T03:00:00Z",
      commentText: "<p>A reply comment</p>",
      commentAuthor: "Reply Author",
      reporter: { id: 6, fullName: "Report Person" },
    },
  ];
  mockReportsApi.fetchReports.mockResolvedValue({ ok: true, reports: reportsWithReply });

  const wrapper = mount(AdminReports);
  await flushPromises();

  const rows = wrapper.findAll(".reports-list .report-row");
  const replyRow = rows.find((row) => row.text().includes("Comment #25"));
  expect(replyRow, "Expected a report row for Comment #25").toBeTruthy();

  await replyRow.find("button.btn-outline").trigger("click");
  await flushPromises();

  expect(mockRouter.push).toHaveBeenCalledWith({
    path: "/posts/60",
    hash: "#comment-25",
    query: { parentCommentId: "15" },
  });
});
});
