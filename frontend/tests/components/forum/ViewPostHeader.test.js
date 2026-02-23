/** @vitest-environment jsdom */
import { mount } from "@vue/test-utils";
import { describe, it, expect } from "vitest";
import PostHeader from "@/components/forum/ViewPostHeader.vue";

describe("PostHeader.vue", () => {
  // TODO: we will most likely add category so test will need an update
  it("renders the correct title, author, role, date, and tags of a post", () => {
    const fakePost = {
      title: "My first post!",
      authorName: "Bobby Bill",
      authorRole: "Moderator",
      authorAvatar: "tree.png",
      createdAt: "2026-02-22 14:20:00",
      tags: [{ Name: "Help" }, { Name: "Official" }, { Name: "Research" }],
    };

    const expectedDateObj = new Date("2026-02-22T14:20:00Z");
    const expectedDateString = expectedDateObj.toLocaleDateString();

    const wrapper = mount(PostHeader, {
      props: {
        post: fakePost,
      },
      global: {
        stubs: {
          UserRole: true,
        },
      },
    });

    expect(wrapper.find(".post-title").text()).toBe(fakePost.title);
    expect(wrapper.find(".author-name").text()).toBe(fakePost.authorName);
    expect(wrapper.find(".date").text()).toBe(expectedDateString);

    const renderedTags = wrapper.findAll(".post-tag");
    expect(renderedTags.length).toBe(3);
    expect(renderedTags[0].text()).toBe("Help");
    expect(renderedTags[1].text()).toBe("Official");
    expect(renderedTags[2].text()).toBe("Research");
  });
});