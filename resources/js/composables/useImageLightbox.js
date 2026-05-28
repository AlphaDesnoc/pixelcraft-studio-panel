import { ref } from "vue";
import {
  lightboxImagesFromAttachments,
  lightboxImagesFromMessages,
  lightboxImagesFromUrls,
} from "@/lib/attachments.js";

export function useImageLightbox() {
  const open = ref(false);
  const index = ref(0);
  const images = ref([]);

  function openAt(list, startIndex = 0) {
    if (!list?.length) {
      return;
    }

    images.value = list;
    index.value = Math.min(Math.max(startIndex, 0), list.length - 1);
    open.value = true;
  }

  function openImage(url, name = "Image") {
    openAt([{ url, name }], 0);
  }

  function openAttachment(attachment, allAttachments) {
    const list = lightboxImagesFromAttachments(allAttachments);
    const startIndex = list.findIndex((item) => item.url === attachment.url);
    openAt(list, startIndex >= 0 ? startIndex : 0);
  }

  function openFromMessages(messages, attachment) {
    const list = lightboxImagesFromMessages(messages);
    const startIndex = list.findIndex((item) => item.url === attachment.url);
    openAt(list, startIndex >= 0 ? startIndex : 0);
  }

  function openFromUrls(urls, startUrl) {
    const list = lightboxImagesFromUrls(urls);
    const startIndex = list.findIndex((item) => item.url === startUrl);
    openAt(list, startIndex >= 0 ? startIndex : 0);
  }

  function close() {
    open.value = false;
  }

  return {
    open,
    index,
    images,
    openAt,
    openImage,
    openAttachment,
    openFromMessages,
    openFromUrls,
    close,
  };
}
