/**
 * AdminReports — unit tests.
 * Covers:
 * - loads and displays all report tags sorted alphabetically
 * - editing a tag triggers PATCH and refreshes the list
 * - deleting a tag calls DELETE and shows a success message
 * - loads active reports and renders post and comment rows
 * - clicking Resolve calls resolveReport and removes the report from the list
 * - clicking Go to routes to the correct post or comment (including parentCommentId)
 * - after Refresh, reports for a deleted post are gone
 * - pagination controls (next page, hidden when empty, sort dropdown options)
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { ref } from "vue";

import AdminReports from "../../../src/components/admin/AdminReports.vue";

const { mockClient } = vi.hoisted(() => ({
  mockClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock("@/api/client", () => ({ default: mockClient }));

const { mockReportsApi } = vi.hoisted(() => ({
  mockReportsApi: { resolveReport: vi.fn(), fetchReports: vi.fn() },
}));

vi.mock("@/api/reports", () => ({
  resolveReport: mockReportsApi.resolveReport,
  fetchReports: mockReportsApi.fetchReports,
}));

const { mockRouter } = vi.hoisted(() => ({
  mockRouter: { push: vi.fn() },
}));
vi.mock("vue-router", () => ({
  useRouter: () => mockRouter,
}));

// userRole must be a real Vue ref so template unwrapping works
vi.mock("@/stores/userStore", () => ({
  userRole: ref("admin"),
}));

vi.mock("@/api/admin", () => ({
  getAdminReportTags: () => mockClient.get("/admin/report-tags").then((r) => r.data.items || []),
  createReportTag: (name) => mockClient.post("/admin/report-tags", { tagName: name }),
  updateReportTag: (id, name) => mockClient.patch(`/admin/report-tags/${id}`, { tagName: name }),
  deleteReportTag: (id) => mockClient.delete(`/admin/report-tags/${id}`),
}));

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

  it("loads all existing report tags and displays them sorted alphabetically", async () => {
    const wrapper = mount(AdminReports);
    await flushPromises();

    expect(mockClient.get).toHaveBeenCalledWith("/admin/report-tags");
    expect(wrapper.find(".admin-table").exists()).toBe(true);
    expect(wrapper.text()).toContain("Spam");
    expect(wrapper.text()).toContain("Harassment");
    expect(wrapper.text()).toContain("Inappropriate");
  });

  it("tag names are sorted alphabetically in the table", async () => {
    const wrapper = mount(AdminReports);
    await flushPromises();

    const cells = wrapper.findAll("tbody td.admin-name");
    const names = cells.map((c) => c.text().trim());
    expect(names).toEqual(["Harassment", "Inappropriate", "Misinformation", "Other", "Spam"]);
  });

  it("editing a tag triggers PATCH and refreshes the list", async () => {
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

  it("deleting a tag calls DELETE and shows a success message", async () => {
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

  it("loads active reports and renders post and comment rows", async () => {
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

  it("renders whatever the API returns without client-side filtering", async () => {
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

 it("clicking Resolve calls resolveReport and removes the report from the list", async () => {
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
  
it("clicking Go to routes to the correct post", async () => {
  const wrapper = mount(AdminReports);
  await flushPromises();

  const rows = wrapper.findAll(".reports-list .report-row");
  const targetRow = rows.find((row) => row.text().includes("Post #99"));
  expect(targetRow, "Expected a report row for Post #99").toBeTruthy();

  await targetRow.find("button.btn-outline").trigger("click"); // Go to
  await flushPromises();

  expect(mockRouter.push).toHaveBeenCalledWith("/posts/99");
});

it("comment report displays stripped comment text (no HTML tags)", async () => {
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

it("comment report shows reporter name, reason, and parent post reference", async () => {
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

it("clicking Go to on a comment report routes to its parent post with hash anchor", async () => {
  const wrapper = mount(AdminReports);
  await flushPromises();

  const rows = wrapper.findAll(".reports-list .report-row");
  const commentRow = rows.find((row) => row.text().includes("Comment #12"));
  expect(commentRow, "Expected a report row for Comment #12").toBeTruthy();

  await commentRow.find("button.btn-outline").trigger("click");
  await flushPromises();

  expect(mockRouter.push).toHaveBeenCalledWith({ path: "/posts/55", hash: "#comment-12", query: {} });
});

it("clicking Go to on a reply comment passes parentCommentId as a query param", async () => {
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

it("13) after Refresh, reports for a deleted post are gone", async () => {
  mockReportsApi.fetchReports
    .mockResolvedValueOnce({
      ok: true,
      reports: [
        { reportId: 1, postId: 10, source: "Post", postTitle: "Deleted Post", reason: "Spam", createdAt: "2026-01-01T00:00:00Z" },
        { reportId: 2, postId: 10, source: "Post", postTitle: "Deleted Post", reason: "Spam", createdAt: "2026-01-01T00:00:00Z" },
        { reportId: 3, postId: 99, source: "Post", postTitle: "Other Post", reason: "Spam", createdAt: "2026-01-01T00:00:00Z" },
      ],
      total: 3,
      page: 1,
      perPage: 25,
    })
    .mockResolvedValueOnce({
      ok: true,
      reports: [
        { reportId: 3, postId: 99, source: "Post", postTitle: "Other Post", reason: "Spam", createdAt: "2026-01-01T00:00:00Z" },
      ],
      total: 1,
      page: 1,
      perPage: 25,
    });

  const wrapper = mount(AdminReports);
  await flushPromises();

  expect(wrapper.findAll(".report-row").length).toBe(3);
  expect(wrapper.text()).toContain("Deleted Post");
  expect(wrapper.text()).toContain("Other Post");

  await wrapper.find("button.btn-refresh").trigger("click");
  await flushPromises();

  expect(wrapper.findAll(".report-row").length).toBe(1);
  expect(wrapper.text()).not.toContain("Deleted Post");
  expect(wrapper.text()).toContain("Other Post");
});
});

// 7 reports for pagination tests
const mockManyReports = Array.from({ length: 7 }, (_, i) => ({
  reportId: i + 1,
  postId: 200 + i,
  source: "Post",
  reason: "Spam",
  createdAt: `2026-03-0${i + 1}T00:00:00Z`,
  contentTitle: `Report Title ${i + 1}`,
}));

describe("AdminReports.vue — Pagination", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockRouter.push.mockClear();
    localStorage.clear();

    mockClient.get.mockImplementation((url) => {
      if (url === "/admin/report-tags")
        return Promise.resolve({ data: { items: mockReportTags } });
      return Promise.resolve({ data: {} });
    });
  });

  it("shows pagination controls when total exceeds perPage", async () => {
    mockReportsApi.fetchReports.mockResolvedValue({
      ok: true,
      reports: mockManyReports.slice(0, 5),
      total: 7,
      page: 1,
      perPage: 5,
    });
    const wrapper = mount(AdminReports);
    await flushPromises();

    const rows = wrapper.findAll(".reports-list .report-row");
    expect(rows.length).toBe(5);

    expect(wrapper.find(".admin-pag").exists()).toBe(true);
    expect(wrapper.text()).toContain("1–5 of 7");
  });

  it("hides pagination when no reports", async () => {
    mockReportsApi.fetchReports.mockResolvedValue({
      ok: true,
      reports: [],
      total: 0,
      page: 1,
      perPage: 25,
    });
    const wrapper = mount(AdminReports);
    await flushPromises();

    expect(wrapper.find(".admin-pag").exists()).toBe(false);
  });

  it("clicking next page emits update and reloads", async () => {
    mockReportsApi.fetchReports
      .mockResolvedValueOnce({
        ok: true,
        reports: mockManyReports.slice(0, 5),
        total: 7,
        page: 1,
        perPage: 5,
      })
      .mockResolvedValueOnce({
        ok: true,
        reports: mockManyReports.slice(5),
        total: 7,
        page: 2,
        perPage: 5,
      });

    const wrapper = mount(AdminReports);
    await flushPromises();

    expect(wrapper.findAll(".reports-list .report-row").length).toBe(5);

    const nextBtn = wrapper.find(".admin-pag-btn[aria-label='Next page']");
    await nextBtn.trigger("click");
    await flushPromises();

    expect(mockReportsApi.fetchReports).toHaveBeenCalledTimes(2);
    const rows = wrapper.findAll(".reports-list .report-row");
    expect(rows.length).toBe(2);
  });

  it("sort dropdown renders with correct options", async () => {
    mockReportsApi.fetchReports.mockResolvedValue({
      ok: true,
      reports: mockManyReports,
      total: mockManyReports.length,
      page: 1,
      perPage: 25,
    });
    const wrapper = mount(AdminReports);
    await flushPromises();

    const sortSelect = wrapper.find(".reports-controls select.sort-select");
    const options = sortSelect.findAll("option");
    expect(options.map((o) => o.element.value)).toEqual(["newest", "oldest"]);
  });
});
