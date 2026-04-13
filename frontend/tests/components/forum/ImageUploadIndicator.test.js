/**
 * ImageUploadIndicator (inside CreatePostModal) — unit tests.
 * Covers:
 * - no upload indicator shown by default
 * - upload indicator appears when image upload starts
 * - upload indicator disappears when image upload finishes
 */
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import CreatePostModal from "@/components/forum/CreatePostModal.vue";

vi.mock("@/api/posts", () => ({
  createPost: vi.fn(async () => ({})),
  getTags: vi.fn(async () => []),
  getCategories: vi.fn(async () => []),
}));

vi.mock("vue-router", () => ({
  useRouter: () => ({
    currentRoute: { value: { params: { id: "123" } } },
    push: vi.fn(),
  }),
}));

vi.mock("@/stores/userStore", () => ({
  fullName: "Test User",
  userAvatar: "/test.png",
  isLoggedIn: true,
  userRole: "User",
  userRoleId: 3,
}));

describe("ImageUploadIndicator (CreatePostModal.vue)", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const createWrapper = () =>
    mount(CreatePostModal, {
      props: {
        show: true,
        loading: false,
        postData: null,
        isRestricted: false,
      },
      global: {
        stubs: {
          teleport: true,
          transition: true,
          UserRole: true,

          // Stub TextEditor so we can trigger upload state changes
          TextEditor: {
            name: "TextEditor",
            props: ["modelValue", "isUploading"],
            emits: ["update:modelValue", "update:isUploading"],
            template: `
              <div data-testid="text-editor-stub">
                <button data-testid="start-upload" @click="$emit('update:isUploading', true)">start</button>
                <button data-testid="finish-upload" @click="$emit('update:isUploading', false)">finish</button>
              </div>
            `,
          },
        },
      },
    });

  const findUploadOverlay = (wrapper) => {
    const overlays = wrapper.findAll(".inner-warning-overlay");
    for (const o of overlays) {
      if (o.text().includes("Uploading image")) return o;
    }
    return null;
  };

  it("does not show upload indicator by default", async () => {
    const wrapper = createWrapper();
    await flushPromises();

    const overlay = findUploadOverlay(wrapper);
    expect(overlay).toBeNull();
  });

  it("shows upload indicator when TextEditor starts uploading", async () => {
    const wrapper = createWrapper();
    await flushPromises();

    const startBtn = wrapper.find('[data-testid="start-upload"]');
    expect(startBtn.exists()).toBe(true);

    await startBtn.trigger("click");
    await flushPromises();

    const overlay = findUploadOverlay(wrapper);
    expect(overlay).not.toBeNull();
    expect(overlay.text()).toContain("Uploading image");
  });

  it("hides upload indicator when TextEditor finishes uploading", async () => {
    const wrapper = createWrapper();
    await flushPromises();

    const startBtn = wrapper.find('[data-testid="start-upload"]');
    const finishBtn = wrapper.find('[data-testid="finish-upload"]');

    await startBtn.trigger("click");
    await flushPromises();

    expect(findUploadOverlay(wrapper)).not.toBeNull();

    await finishBtn.trigger("click");
    await flushPromises();

    expect(findUploadOverlay(wrapper)).toBeNull();
  });
});