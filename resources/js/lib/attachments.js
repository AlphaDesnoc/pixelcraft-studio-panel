export function isImageAttachment(attachment) {
  if (attachment?.mime_type?.startsWith("image/")) {
    return true;
  }

  return /\.(png|jpe?g|gif|webp|bmp|svg|avif)$/i.test(
    attachment?.original_name ?? "",
  );
}

export function isVideoAttachment(attachment) {
  if (attachment?.mime_type?.startsWith("video/")) {
    return true;
  }

  return /\.(mp4|webm|mov|m4v|ogv)(\?|$)/i.test(
    attachment?.url ?? attachment?.original_name ?? "",
  );
}

export function isPdfAttachment(attachment) {
  if (attachment?.mime_type === "application/pdf") {
    return true;
  }

  return /\.pdf(\?|$)/i.test(
    attachment?.url ?? attachment?.original_name ?? "",
  );
}

export function isImageSource(src, name = "") {
  if (!src) {
    return false;
  }

  return /\.(png|jpe?g|gif|webp|bmp|svg|avif)(\?|$)/i.test(src)
    || /\.(png|jpe?g|gif|webp|bmp|svg|avif)$/i.test(name);
}

export function attachmentToLightboxImage(attachment) {
  return {
    url: attachment.url,
    name: attachment.original_name ?? "Image",
  };
}

export function lightboxImagesFromAttachments(attachments) {
  return (attachments ?? [])
    .filter(isImageAttachment)
    .map(attachmentToLightboxImage);
}

export function lightboxImagesFromMessages(messages) {
  const images = [];

  for (const message of messages ?? []) {
    for (const attachment of message.attachments ?? []) {
      if (isImageAttachment(attachment)) {
        images.push(attachmentToLightboxImage(attachment));
      }
    }
  }

  return images;
}

export function lightboxImagesFromUrls(urls) {
  return (urls ?? [])
    .filter((url) => isImageSource(url))
    .map((url, index) => ({
      url,
      name: `Image ${index + 1}`,
    }));
}
