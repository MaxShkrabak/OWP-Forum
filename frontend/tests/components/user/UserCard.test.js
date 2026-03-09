import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

const { mockFetchUserStats } = vi.hoisted(() => ({
  mockFetchUserStats: vi.fn(),
}));

vi.mock("@/api/users", () => ({
  fetchUserStats: mockFetchUserStats,
}));

vi.mock("@/stores/userStore", () => ({
  isLoggedIn: { value: true },
  fullName: { value: "Test User" },
  userAvatar: { value: "" },
  userRole: { value: "User" },
  uid: { value: 42 },
}));

import UserCard from "@/components/user/UserCard.vue";

describe("UserCard.vue — user stats", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("displays fetched stats for the logged-in user", async () => {
    mockFetchUserStats.mockResolvedValueOnce({
      ok: true,
      stats: { postCount: 5, voteScore: 12, commentCount: 23 },
    });

    const wrapper = mount(UserCard, {
      global: {
        stubs: { RouterLink: true, UserRole: true },
      },
    });
    await flushPromises();

    const statValues = wrapper.findAll(".stat-value");
    expect(statValues[0].text()).toBe("5");
    expect(statValues[1].text()).toBe("12");
    expect(statValues[2].text()).toBe("23");
    expect(mockFetchUserStats).toHaveBeenCalledWith(42);
  });

  it("uses userId prop when provided (other user's profile)", async () => {
    mockFetchUserStats.mockResolvedValueOnce({
      ok: true,
      stats: { postCount: 10, voteScore: 3, commentCount: 7 },
    });

    const wrapper = mount(UserCard, {
      props: { userId: 99 },
      global: {
        stubs: { RouterLink: true, UserRole: true },
      },
    });
    await flushPromises();

    expect(mockFetchUserStats).toHaveBeenCalledWith(99);
    const statValues = wrapper.findAll(".stat-value");
    expect(statValues[0].text()).toBe("10");
    expect(statValues[1].text()).toBe("3");
    expect(statValues[2].text()).toBe("7");
  });

  it("re-fetches stats when userId prop changes", async () => {
    mockFetchUserStats
      .mockResolvedValueOnce({
        ok: true,
        stats: { postCount: 2, voteScore: 5, commentCount: 3 },
      })
      .mockResolvedValueOnce({
        ok: true,
        stats: { postCount: 8, voteScore: 20, commentCount: 15 },
      });

    const wrapper = mount(UserCard, {
      props: { userId: 10 },
      global: { stubs: { RouterLink: true, UserRole: true } },
    });
    await flushPromises();

    let statValues = wrapper.findAll(".stat-value");
    expect(statValues[0].text()).toBe("2");
    expect(statValues[1].text()).toBe("5");
    expect(statValues[2].text()).toBe("3");
    expect(mockFetchUserStats).toHaveBeenCalledWith(10);

    await wrapper.setProps({ userId: 20 });
    await flushPromises();

    expect(mockFetchUserStats).toHaveBeenCalledWith(20);
    statValues = wrapper.findAll(".stat-value");
    expect(statValues[0].text()).toBe("8");
    expect(statValues[1].text()).toBe("20");
    expect(statValues[2].text()).toBe("15");
  });

  it("shows the Reputation label instead of Votes or Likes", async () => {
    mockFetchUserStats.mockResolvedValueOnce({
      ok: true,
      stats: { postCount: 0, voteScore: 0, commentCount: 0 },
    });

    const wrapper = mount(UserCard, {
      global: {
        stubs: { RouterLink: true, UserRole: true },
      },
    });
    await flushPromises();

    const labels = wrapper.findAll(".stat-label");
    const labelTexts = labels.map((l) => l.text());
    expect(labelTexts).toContain("Reputation");
    expect(labelTexts).not.toContain("Votes");
    expect(labelTexts).not.toContain("Likes");
  });

  it("keeps stats at 0 when the API call fails", async () => {
    mockFetchUserStats.mockRejectedValueOnce(new Error("Network error"));

    const wrapper = mount(UserCard, {
      global: {
        stubs: { RouterLink: true, UserRole: true },
      },
    });
    await flushPromises();

    const statValues = wrapper.findAll(".stat-value");
    expect(statValues[0].text()).toBe("0");
    expect(statValues[1].text()).toBe("0");
    expect(statValues[2].text()).toBe("0");
  });
});
