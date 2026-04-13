/**
 * TextEditor — unit tests.
 * Covers:
 * - clear all formatting across a full selection
 * - clear formatting on a partial selection only
 * - convert heading back to a standard paragraph
 */
import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, beforeEach } from "vitest";
import TextEditor from "@/components/forum/TextEditor.vue";

describe("TextEditor.vue", () => {
  let wrapper;
  let editor;
  let clearButton;

  beforeEach(() => {
    wrapper = mount(TextEditor, {
      props: {
        modelValue: "",
        isUploading: false,
      },
    });
  });

  const setupTest = async () => {
    await flushPromises();
    editor = wrapper.vm.editor;
    clearButton = wrapper.find('button[title="Clear All Formatting"]');
  };

  it("clears all styling and links from a fully highlighted sentence", async () => {
    await setupTest();

    editor.commands.setContent(
      "<p>This is a post I made to test clearing: " +
        "<strong>Bold</strong> " +
        "<em>Italics </em>" +
        "<s>Strike</s> " +
        "<u>Underline</u> " +
        '<span style="color: rgb(213, 26, 26);">RedText </span>' +
        "<span>" +
        '<mark data-color="#cd0e0e" style="background-color: rgb(205, 14, 14); color: inherit;">RedHighlight</mark> ' +
        "</span>" +
        '<a target="_blank" rel="noopener noreferrer nofollow" class="my-custom-link" href="https://RealYoutube">' +
        "<span>Youtube.com</span>" +
        "</a></p>",
    );

    editor.commands.selectAll();

    await clearButton.trigger("click");
    expect(editor.getHTML()).toBe(
      "<p>This is a post I made to test clearing: Bold Italics Strike Underline RedText RedHighlight Youtube.com</p>",
    );
  });

  it("clears styling only from the specifically highlighted section", async () => {
    await setupTest();
    editor.commands.setContent("<p><strong>ABCDEF</strong></p>");

    editor.commands.setTextSelection({ from: 4, to: 7 });

    await clearButton.trigger("click");
    expect(editor.getHTML()).toBe("<p><strong>ABC</strong>DEF</p>");
  });

  it("converts a heading back to a standard paragraph", async () => {
    await setupTest();
    editor.commands.setContent("<h1>My Header Title</h1>");

    editor.commands.selectAll();

    await clearButton.trigger("click");
    expect(editor.getHTML()).toBe("<p>My Header Title</p><p></p>"); // expects another paragraph due to way editor works
  });
});