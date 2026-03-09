import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import AdminReports from "@/components/admin/AdminReports.vue";

// Used by "Go to" button
vi.mock("vue-router", () => ({
  useRouter: () => ({ push: vi.fn() }),
}));

// Used by loadReportTags/loadReports/enrichReports
const clientGet = vi.fn();
vi.mock("@/api/client", () => ({
  default: { get: (...args) => clientGet(...args) },
}));

// Used by Resolve button
const resolveReportMock = vi.fn();
vi.mock("@/api/reports", () => ({
  resolveReport: (...args) => resolveReportMock(...args),
}));

function makeReport(overrides = {}) {
  return {
    reportId: 1,
    postId: 10,
    commentId: null,
    reason: "Spam",
    createdAt: "2026-01-01T00:00:00Z",
    source: "Post",
    reporterId: 111,
    reporterName: "Reporter",
    contentTitle: "Test Post",
    contentAuthorId: 222,
    contentAuthorName: "Author",
    ...overrides,
  };
}

describe("AdminReports - acceptance checks", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("shows reports and removes one after Resolve", async () => {
    clientGet.mockImplementation((url) => {
      if (url === "/admin/report-tags") return Promise.resolve({ data: { items: [] } });

      if (url === "/admin/reports") {
        return Promise.resolve({
          data: {
            ok: true,
            reports: [
              makeReport({ reportId: 1, postId: 10, contentTitle: "Post A" }),
              makeReport({ reportId: 2, postId: 11, contentTitle: "Post B" }),
            ],
          },
        });
      }

      // enrichReports() calls this
      if (url.startsWith("/get-post/")) {
        const postId = Number(url.split("/").pop());
        return Promise.resolve({
          data: { ok: true, post: { title: `Post ${postId}`, authorId: 500 + postId, authorName: `Author ${postId}` } },
        });
      }

      return Promise.reject(new Error(`Unexpected GET ${url}`));
    });

    resolveReportMock.mockResolvedValue({ ok: true });

    const wrapper = mount(AdminReports);
    await flushPromises();

    expect(wrapper.text()).toContain("Post A");
    expect(wrapper.text()).toContain("Post B");
    expect(wrapper.findAll(".report-row").length).toBe(2);

    const rows = wrapper.findAll(".report-row");
    await rows[0].find("button.btn-solid").trigger("click");
    await flushPromises();

    expect(resolveReportMock).toHaveBeenCalledWith(1);
    expect(wrapper.text()).not.toContain("Post A");
    expect(wrapper.text()).toContain("Post B");
    expect(wrapper.findAll(".report-row").length).toBe(1);
  });

  it("after Refresh, reports for a deleted post are gone", async () => {
    let reportsCallCount = 0;

    clientGet.mockImplementation((url) => {
      if (url === "/admin/report-tags") return Promise.resolve({ data: { items: [] } });

      if (url === "/admin/reports") {
        reportsCallCount += 1;

        if (reportsCallCount === 1) {
          return Promise.resolve({
            data: {
              ok: true,
              reports: [
                makeReport({ reportId: 1, postId: 10, contentTitle: "Deleted Post" }),
                makeReport({ reportId: 2, postId: 10, contentTitle: "Deleted Post" }),
                makeReport({ reportId: 3, postId: 99, contentTitle: "Other Post" }),
              ],
            },
          });
        }

        return Promise.resolve({
          data: {
            ok: true,
            reports: [makeReport({ reportId: 3, postId: 99, contentTitle: "Other Post" })],
          },
        });
      }

      // enrichReports() calls this
      if (url.startsWith("/get-post/")) {
        const postId = Number(url.split("/").pop());
        return Promise.resolve({
          data: {
            ok: true,
            post: {
              title: postId === 10 ? "Deleted Post" : "Other Post",
              authorId: 500 + postId,
              authorName: `Author ${postId}`,
            },
          },
        });
      }

      return Promise.reject(new Error(`Unexpected GET ${url}`));
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