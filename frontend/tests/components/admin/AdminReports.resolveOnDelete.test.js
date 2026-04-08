import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import AdminReports from "@/components/admin/AdminReports.vue";

vi.mock("vue-router", () => ({
  useRouter: () => ({ push: vi.fn() }),
}));

const clientGet = vi.fn();
vi.mock("@/api/client", () => ({
  default: { get: (...args) => clientGet(...args) },
}));

const resolveReportMock = vi.fn();
const fetchReportsMock = vi.fn();
vi.mock("@/api/reports", () => ({
  resolveReport: (...args) => resolveReportMock(...args),
  fetchReports: (...args) => fetchReportsMock(...args),
  normalizeReport: (r) => r,
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
      return Promise.reject(new Error(`Unexpected GET ${url}`));
    });

    fetchReportsMock.mockResolvedValue({
      ok: true,
      reports: [
        makeReport({ reportId: 1, postId: 10, contentTitle: "Post A" }),
        makeReport({ reportId: 2, postId: 11, contentTitle: "Post B" }),
      ],
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
    clientGet.mockImplementation((url) => {
      if (url === "/admin/report-tags") return Promise.resolve({ data: { items: [] } });
      return Promise.reject(new Error(`Unexpected GET ${url}`));
    });

    fetchReportsMock
      .mockResolvedValueOnce({
        ok: true,
        reports: [
          makeReport({ reportId: 1, postId: 10, contentTitle: "Deleted Post" }),
          makeReport({ reportId: 2, postId: 10, contentTitle: "Deleted Post" }),
          makeReport({ reportId: 3, postId: 99, contentTitle: "Other Post" }),
        ],
      })
      .mockResolvedValueOnce({
        ok: true,
        reports: [makeReport({ reportId: 3, postId: 99, contentTitle: "Other Post" })],
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