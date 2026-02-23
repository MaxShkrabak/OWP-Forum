/**
 * Manage Categories (Admin) — unit tests.
 * Node environment only (no DOM). Tests duplicate-prevention logic and category slugify util.
 */
import { describe, it, expect } from "vitest";
import { slugifyCategoryName } from "@/utils/slugify";

// Same duplicate-check logic as AdminCategories.vue nameExists (for "Prevent duplicates" / BB-151)
function categoryNameExists(categories, name, excludeId = null) {
  const n = (name || "").trim().toLowerCase();
  if (!n) return false;
  return categories.some(
    (c) =>
      (c.name || "").trim().toLowerCase() === n && c.categoryId !== excludeId
  );
}

describe("Manage Categories (Admin) — duplicate prevention", () => {
  const categories = [
    { categoryId: 1, name: "General", usableByRoleID: 1 },
    { categoryId: 2, name: "Help", usableByRoleID: 1 },
    { categoryId: 3, name: "Random", usableByRoleID: 2 },
  ];

  it("returns true when another category has the same name (add case)", () => {
    expect(categoryNameExists(categories, "Help")).toBe(true);
    expect(categoryNameExists(categories, "help")).toBe(true);
    expect(categoryNameExists(categories, "  General  ")).toBe(true);
  });

  it("returns false when name is unique", () => {
    expect(categoryNameExists(categories, "News")).toBe(false);
    expect(categoryNameExists(categories, "Announcements")).toBe(false);
  });

  it("returns false when editing same category (excludeId)", () => {
    expect(categoryNameExists(categories, "Help", 2)).toBe(false);
    expect(categoryNameExists(categories, "Random", 3)).toBe(false);
  });

  it("returns true when editing to a name used by another category", () => {
    expect(categoryNameExists(categories, "General", 2)).toBe(true);
    expect(categoryNameExists(categories, "Help", 1)).toBe(true);
  });

  it("returns false for empty or whitespace name", () => {
    expect(categoryNameExists(categories, "")).toBe(false);
    expect(categoryNameExists(categories, "   ")).toBe(false);
    expect(categoryNameExists(categories, null)).toBe(false);
  });

  it("handles empty category list", () => {
    expect(categoryNameExists([], "Help")).toBe(false);
  });
});

describe("Manage Categories (Admin) — category slugify", () => {
  it("slugifies category name for URLs", () => {
    expect(slugifyCategoryName("Help")).toBe("help");
    expect(slugifyCategoryName("Random")).toBe("random");
    expect(slugifyCategoryName("General")).toBe("general");
  });

  it("replaces spaces and special chars with dashes", () => {
    expect(slugifyCategoryName("Announcements & News")).toBe(
      "announcements-and-news"
    );
    // & is replaced with "and", then non-alphanumerics become dashes → "qanda"
    expect(slugifyCategoryName("Q&A")).toBe("qanda");
  });

  it("trims leading and trailing dashes", () => {
    expect(slugifyCategoryName("  Help  ")).toBe("help");
    expect(slugifyCategoryName("---test---")).toBe("test");
  });
});
