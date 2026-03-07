/** @vitest-environment jsdom */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { ref } from "vue";
import { fetchReports, resolveReport } from "@/api/reports";
import ViewReportsButton from "@/components/admin/ViewReportsButton.vue";

const { mockRouter } = vi.hoisted(() => ({ mockRouter: { push: vi.fn() } }));
vi.mock("vue-router", () => ({ useRouter: () => mockRouter }));

// sample data
const sampleReports = [
  {
    reportId: 1,
    postId: 99,
    source: "Post",
    postTitle: "First post title",
    postAuthor: "alice Author",
    reporter: { fullName: "Bob Joe" },
    createdAt: "2026-02-28T12:00:00Z",
    reason: "Spam",
  },
  {
    reportId: 2,
    postId: 100,
    source: "Comment",
    commentText: "This is a bad comment",
    commentAuthor: "charlie Author",
    reporter: { fullName: "Joe Smith" },
    createdAt: "2026-02-28T13:00:00Z",
    reason: "Harassment",
  },
];

vi.mock("@/api/reports", () => ({
  fetchReports: vi.fn(),
  resolveReport: vi.fn(),
}));

vi.mock("@/stores/userStore", () => ({
  userRole: ref("admin"),
}));



describe("ViewReportsButton.vue", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockRouter.push.mockClear();

    global.bootstrap = { Toast: class { constructor(el){ } show(){} } };

    fetchReports.mockResolvedValue({ ok: true, reports: sampleReports });
    resolveReport.mockResolvedValue({ ok: true });
  });

  it("should display accurate active reports", async () => {
    const wrapper = mount(ViewReportsButton, {
      global: { stubs: { teleport: true } },
    });

    await flushPromises();

    expect(wrapper.text()).toContain("Ticket: #1");
    expect(wrapper.text()).toContain("First post title");
    expect(wrapper.text()).toContain("alice Author");
    expect(wrapper.text()).toContain("Bob Joe");
    expect(wrapper.text()).toContain("Spam");

    expect(wrapper.text()).toContain("Ticket: #2");
    expect(wrapper.text()).toContain("This is a bad comment");
    expect(wrapper.text()).toContain("charlie Author");
    expect(wrapper.text()).toContain("Joe Smith");
    expect(wrapper.text()).toContain("Harassment");

    const items = wrapper.findAll("li.list-group-item");
    expect(items.length).toBe(sampleReports.length);
  });

  it("should route to the correct post", async () => {
    const wrapper = mount(ViewReportsButton, {
      global: { stubs: { teleport: true } },
    });
    await flushPromises();

    const goButtons = wrapper
      .findAll(".report-cta-btn")
      .filter((w) => w.text().includes("Go To"));

    expect(goButtons.length).toBe(sampleReports.length);
    await goButtons[0].trigger("click");

    expect(mockRouter.push).toHaveBeenCalledWith("/posts/99");
  });

  it("should resolve the report and clear it from UI",
    async () => {
      const wrapper = mount(ViewReportsButton, {
        global: { stubs: { teleport: true } },
      });
      await flushPromises();

      const resolveButtons = wrapper
        .findAll(".report-cta-btn")
        .filter((w) => w.text().includes("Resolve"));

      expect(resolveButtons.length).toBe(sampleReports.length);
      await resolveButtons[0].trigger("click");
      await flushPromises();

      expect(resolveReport).toHaveBeenCalledWith(1);

      const remaining = wrapper.findAll("li.list-group-item");
      expect(remaining.length).toBe(sampleReports.length - 1);

      remaining.forEach((item) => {
        expect(item.text()).not.toContain("Ticket: #1");
      });
    }
  );
});

