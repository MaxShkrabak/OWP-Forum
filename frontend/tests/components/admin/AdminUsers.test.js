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
  { userId: 1, firstName: "Jane", lastName: "Doe", email: "jane@example.com", roleName: "User", roleId: 1, isBanned: 0, banType: null, bannedUntil: null },
  { userId: 2, firstName: "John", lastName: "Smith", email: "john@example.com", roleName: "Admin", roleId: 4, isBanned: 0, banType: null, bannedUntil: null },
];

// Current admin user ID used in /admin/me mock
const CURRENT_ADMIN_ID = 2;

function setupMocks(users = mockUsers) {
  mockClient.get.mockImplementation((url) => {
    if (url === '/me') {
      return Promise.resolve({ data: { user: { userId: CURRENT_ADMIN_ID } } });
    }
    // /admin/users
    return Promise.resolve({ data: { users: users.map(u => ({ ...u })) } });
  });
  mockClient.patch.mockResolvedValue({});
}

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
    setupMocks();
  });

  it("loads users and shows Ban button only for non-admin active users", async () => {
    const wrapper = mount(AdminUsers);
    await flushPromises();
    const banButtons = wrapper.findAll(".btn-ban");
    expect(banButtons.length).toBe(1); // Only Jane (User), not John (Admin)
  });

  it("does not show Ban button for admin users (BB-356)", async () => {
    const wrapper = mount(AdminUsers);
    await flushPromises();
    const rows = wrapper.findAll(".admin-table tbody tr");
    // Find John Smith's row (the admin user) by name
    const adminRow = rows.find(r => r.text().includes("John"));
    expect(adminRow).toBeDefined();
    expect(adminRow.find(".btn-ban").exists()).toBe(false);
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
    vi.spyOn(window, "alert").mockImplementation(() => {});
  });

  it("displays 'No users found' when searching for a non-existent user", async () => {
    mockClient.get.mockImplementation((url) => {
      if (url === '/me') {
        return Promise.resolve({ data: { user: { userId: CURRENT_ADMIN_ID } } });
      }
      return Promise.resolve({ data: { users: [] } });
    });

    const wrapper = mount(AdminUsers);
    await flushPromises();

    // Assert the empty state message appears
    expect(wrapper.text()).toContain("No users found");
  });

  it("renders role select dropdowns for each user", async () => {
    setupMocks();
    const wrapper = mount(AdminUsers);
    await flushPromises();

    const selects = wrapper.findAll(".role-select");
    expect(selects.length).toBe(mockUsers.length);
  });

  it("disables role select for the current admin user's own row", async () => {
    setupMocks();
    const wrapper = mount(AdminUsers);
    await flushPromises();

    const rows = wrapper.findAll(".admin-table tbody tr");
    // John (User_ID=2) is the current admin
    const adminRow = rows[1];
    const select = adminRow.find(".role-select");
    expect(select.exists()).toBe(true);
    expect(select.element.disabled).toBe(true);
  });

  it("does not disable role select for other users", async () => {
    setupMocks();
    const wrapper = mount(AdminUsers);
    await flushPromises();

    const rows = wrapper.findAll(".admin-table tbody tr");
    // Jane (User_ID=1) is not the current admin
    const janeRow = rows[0];
    const select = janeRow.find(".role-select");
    expect(select.exists()).toBe(true);
    expect(select.element.disabled).toBe(false);
  });
});
