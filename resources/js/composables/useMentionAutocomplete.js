import { computed, nextTick, ref } from "vue";

const MENTION_PATTERN = /(?:^|\s)@([a-z0-9._-]*)$/i;

export function memberPseudo(member) {
  if (member?.type === "rank") {
    return member.slug ?? member.pseudo ?? "";
  }
  if (member?.pseudo) {
    return member.pseudo;
  }
  if (member?.email) {
    return member.email.split("@")[0];
  }
  return "";
}

export function extractMentionQuery(text, cursor) {
  const before = text.slice(0, cursor);
  const match = before.match(MENTION_PATTERN);
  if (!match) {
    return null;
  }

  return {
    query: match[1],
    start: before.length - match[1].length - 1,
    end: cursor,
  };
}

function resolveTextareaElement(textareaRef) {
  const target = textareaRef.value;
  if (!target) {
    return null;
  }
  return target.$el ?? target;
}

export function useMentionAutocomplete({
  textRef,
  textareaRef,
  candidatesRef,
  onInput,
}) {
  const open = ref(false);
  const query = ref("");
  const range = ref({ start: 0, end: 0 });
  const activeIndex = ref(0);

  const suggestions = computed(() => {
    if (!open.value) {
      return [];
    }

    const q = query.value.toLowerCase();
    const items = (candidatesRef.value ?? []).map((member) => ({
      ...member,
      pseudo: memberPseudo(member),
    }));

    const rankItems = items.filter((item) => item.type === "rank" && item.pseudo);
    const userItems = items.filter((item) => item.type !== "rank" && item.pseudo);

    const filterItem = (item) => {
      if (!q) {
        return true;
      }
      const pseudo = item.pseudo.toLowerCase();
      const name = (item.name ?? "").toLowerCase();
      return pseudo.startsWith(q) || name.includes(q);
    };

    return [...rankItems.filter(filterItem), ...userItems.filter(filterItem)].slice(
      0,
      8,
    );
  });

  function close() {
    open.value = false;
    query.value = "";
    activeIndex.value = 0;
  }

  function syncFromTextarea() {
    const el = resolveTextareaElement(textareaRef);
    const cursor = el?.selectionStart ?? textRef.value.length;
    const ctx = extractMentionQuery(textRef.value, cursor);

    if (!ctx) {
      close();
      return;
    }

    open.value = true;
    query.value = ctx.query;
    range.value = { start: ctx.start, end: ctx.end };
    activeIndex.value = 0;
  }

  function handleInput() {
    syncFromTextarea();
    onInput?.();
  }

  function insertMention(member) {
    const pseudo = memberPseudo(member);
    if (!pseudo) {
      return;
    }

    const before = textRef.value.slice(0, range.value.start);
    const after = textRef.value.slice(range.value.end);
    const insert = `@${pseudo} `;
    textRef.value = before + insert + after;
    close();

    nextTick(() => {
      const el = resolveTextareaElement(textareaRef);
      if (!el) {
        return;
      }
      const pos = before.length + insert.length;
      el.focus();
      el.setSelectionRange(pos, pos);
    });
  }

  function handleKeydown(event) {
    if (!open.value || suggestions.value.length === 0) {
      return false;
    }

    if (event.key === "ArrowDown") {
      event.preventDefault();
      activeIndex.value = (activeIndex.value + 1) % suggestions.value.length;
      return true;
    }

    if (event.key === "ArrowUp") {
      event.preventDefault();
      activeIndex.value =
        (activeIndex.value - 1 + suggestions.value.length) %
        suggestions.value.length;
      return true;
    }

    if (event.key === "Enter" || event.key === "Tab") {
      event.preventDefault();
      insertMention(suggestions.value[activeIndex.value]);
      return true;
    }

    if (event.key === "Escape") {
      event.preventDefault();
      close();
      return true;
    }

    return false;
  }

  return {
    open,
    suggestions,
    activeIndex,
    handleInput,
    handleKeydown,
    insertMention,
    close,
  };
}
