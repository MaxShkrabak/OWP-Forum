/** @vitest-environment jsdom */
/**
 * Ban User (Admin) — unit tests.
 * Ban date formatting (no DOM) + AdminUsers component DOM tests.
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import {
  formatBannedUntilDate,
  formatBannedUntilDateTime,
} from "@/utils/banDate";
import AdminUsers from "@/components/admin/AdminUsers.vue";

const { mockClient } = vi.hoisted(() => ({
  mockClient: { get: vi.fn(), patch: vi.fn() },
}));
vi.mock("@/api/client", () => ({ default: mockClient }));

const mockUsers = [
  { User_ID: 1, FirstName: "Jane", LastName: "Doe", Email: "jane@example.com", RoleName: "User", IsBanned: 0, BanType: null, BannedUntil: null },
  { User_ID: 2, FirstName: "John", LastName: "Smith", Email: "john@example.com", RoleName: "Admin", IsBanned: 0, BanType: null, BannedUntil: null },
];

describe("Ban User (Admin) — ban date formatting", () => {
  it("formatBannedUntilDateTime returns empty string for null/empty", () => {
    expect(formatBannedUntilDateTime(null)).toBe("");
    expect(formatBannedUntilDateTime("")).toBe("");
    expect(formatBannedUntilDateTime("   ")).toBe("");
  });

  it("formatBannedUntilDateTime parses ISO date and appends UTC", () => {
    const result = formatBannedUntilDateTime("2025-03-15T14:30:00");
    expect(result).toContain("UTC");
    expect(result).toMatch(/\d/); // has some date part
  });

  it("formatBannedUntilDateTime accepts short dateStyle and timeStyle options", () => {
    const result = formatBannedUntilDateTime("2025-03-15T14:30:00", {
      dateStyle: "short",
      timeStyle: "short",
    });
    expect(result).toContain("UTC");
    expect(result.length).toBeGreaterThan(0);
  });

  it("formatBannedUntilDate returns empty string for null/empty", () => {
    expect(formatBannedUntilDate(null)).toBe("");
    expect(formatBannedUntilDate("")).toBe("");
  });

  it("formatBannedUntilDate parses YYYY-MM-DD and returns formatted date", () => {
    const result = formatBannedUntilDate("2025-03-15");
    expect(result.length).toBeGreaterThan(0);
    expect(result).toMatch(/\d/);
  });
});

describe("Ban User (Admin) — AdminUsers.vue DOM", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(window, "alert").mockImplementation(() => {});
    mockClient.get.mockResolvedValue({ data: { users: [...mockUsers] } });
    mockClient.patch.mockResolvedValue({});
  });

  it("loads users and shows Ban button for each active user", async () => {
    const wrapper = mount(AdminUsers);
    await flushPromises();
    const banButtons = wrapper.findAll(".btn-ban");
    expect(banButtons.length).toBe(2);
  });

  it("opens ban modal when Ban is clicked and confirm updates row to banned state", async () => {
    const wrapper = mount(AdminUsers);
    await flushPromises();
    await wrapper.find(".btn-ban").trigger("click");
    expect(wrapper.find(".modal-overlay").exists()).toBe(true);
    expect(wrapper.text()).toContain("Ban user");
    await wrapper.find(".btn-modal-confirm").trigger("click");
    await flushPromises();
    const firstRow = wrapper.findAll(".admin-table tbody tr")[0];
    expect(firstRow.classes()).toContain("row-banned");
    expect(firstRow.find(".btn-unban").exists()).toBe(true);
  });
});

describe("Assign Role (Admin)", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("displays 'No users found' when searching for a non-existent user", async () => {
    // Mock the API returning an empty array for the search
    mockClient.get.mockResolvedValue({ data: { users: [] } });
    
    const wrapper = mount(AdminUsers);
    await flushPromises();
    
    // Find the search input and type a fake name
    const searchInput = wrapper.find('input[type="text"]'); // Update selector if needed
    if (searchInput.exists()) {
      await searchInput.setValue("ThisUserDoesNotExist");
      await flushPromises();
    }
    
    // Assert the empty state message appears
    expect(wrapper.text()).toContain("No users found");
  });
});