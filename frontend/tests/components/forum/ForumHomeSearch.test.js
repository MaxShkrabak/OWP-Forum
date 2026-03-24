import { describe, it, expect } from "vitest";

function normalize(str) {
  return (str ?? "").toString().trim().toLowerCase();
}

function postMatchesGeneralSearch(post, categoryName, q) {
  if (!q) return true;
  const nq = normalize(q);

  const title = normalize(post?.title);
  const author = normalize(post?.authorName);
  const cat = normalize(categoryName);
  const tags = Array.isArray(post?.tags) ? post.tags : [];
  const authorRole = normalize(post?.authorRole);

  return (
    title.includes(nq) ||
    author.includes(nq) ||
    cat.includes(nq) ||
    authorRole.includes(nq) ||
    tags.some((t) => normalize(t).includes(nq))
  );
}

describe("ForumHome general search", () => {
  it("finds posts by title", () => {
    const post = { title: "My First Post", authorName: "Alice", tags: [] };
    expect(postMatchesGeneralSearch(post, "Announcements", "first")).toBe(true);
  });

  it("finds posts by author name", () => {
    const post = { title: "Hello", authorName: "Bob Barker", tags: [] };
    expect(postMatchesGeneralSearch(post, "General", "bob")).toBe(true);
  });

  it("finds posts by tag", () => {
    const post = { title: "Hello", authorName: "Alice", tags: ["Bob"] };
    expect(postMatchesGeneralSearch(post, "General", "bob")).toBe(true);
  });

  it("finds posts by category name", () => {
    const post = { title: "Hello", authorName: "Alice", tags: [] };
    expect(postMatchesGeneralSearch(post, "Research", "rese")).toBe(true);
  });

  it("finds posts by authorRole (nice-to-have)", () => {
    const post = { title: "Hello", authorName: "Alice", authorRole: "Admin", tags: [] };
    expect(postMatchesGeneralSearch(post, "General", "admin")).toBe(true);
  });

  it("returns false when nothing matches", () => {
    const post = { title: "Hello", authorName: "Alice", tags: ["Vue"] };
    expect(postMatchesGeneralSearch(post, "General", "kubernetes")).toBe(false);
  });

  it("empty query matches all", () => {
    const post = { title: "Hello", authorName: "Alice", tags: [] };
    expect(postMatchesGeneralSearch(post, "General", "")).toBe(true);
  });
});