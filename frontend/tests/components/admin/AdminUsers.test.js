/**
 * Ban User (Admin) — unit tests.
 * Tests the ban date formatting used by the Admin panel and banned-user bsdanner.
 */
import { describe, it, expect } from "vitest";
import {
  formatBannedUntilDate,
  formatBannedUntilDateTime,
} from "@/utils/banDate";

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
