<script setup>
defineProps({
  isMine: { type: Boolean, default: false },
  clusterStart: { type: Boolean, default: true },
  clusterEnd: { type: Boolean, default: true },
  senderName: { type: String, default: "" },
  showSenderName: { type: Boolean, default: false },
  showAvatar: { type: Boolean, default: false },
  avatarInitials: { type: String, default: "?" },
  avatarUrl: { type: String, default: "" },
  highlight: { type: Boolean, default: false },
  mentionsMe: { type: Boolean, default: false },
  pinned: { type: Boolean, default: false },
});
</script>

<template>
  <div
    class="wa-chat-row group"
    :class="[
      isMine ? 'wa-chat-row--mine' : 'wa-chat-row--theirs',
      clusterStart ? 'wa-chat-row--cluster-start' : '',
      clusterEnd ? 'wa-chat-row--cluster-end' : '',
      highlight ? 'wa-chat-row--highlight' : '',
    ]"
  >
    <template v-if="!isMine">
      <div
        v-if="showAvatar"
        class="wa-chat-avatar"
        :title="senderName"
        :aria-label="senderName"
      >
        <img
          v-if="avatarUrl"
          :src="avatarUrl"
          alt=""
          class="h-full w-full rounded-full object-cover"
        />
        <template v-else>{{ avatarInitials }}</template>
      </div>
      <div v-else class="wa-chat-avatar-spacer" aria-hidden="true" />
    </template>

    <div class="wa-chat-stack">
      <p v-if="showSenderName && senderName" class="wa-chat-sender-name">
        {{ senderName }}
      </p>

      <div
        class="wa-chat-bubble"
        :class="[
          isMine ? 'wa-chat-bubble--mine' : 'wa-chat-bubble--theirs',
          clusterStart ? 'wa-chat-bubble--cluster-start' : 'wa-chat-bubble--cluster-continued-top',
          clusterEnd ? 'wa-chat-bubble--cluster-end' : 'wa-chat-bubble--cluster-continued-bottom',
          pinned ? 'wa-chat-bubble--pinned' : '',
          mentionsMe ? 'wa-chat-bubble--mention' : '',
        ]"
      >
        <div v-if="$slots.toolbar" class="wa-chat-bubble-toolbar">
          <slot name="toolbar" />
        </div>

        <slot name="reply" />

        <div class="wa-chat-bubble-content">
          <slot />
        </div>

        <div v-if="$slots.meta || $slots.footer" class="wa-chat-bubble-meta">
          <slot name="footer" />
          <slot name="meta" />
        </div>
      </div>

      <div v-if="$slots.after" class="wa-chat-after">
        <slot name="after" />
      </div>
    </div>
  </div>
</template>
