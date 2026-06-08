import { computed, ref } from "vue";
import axios from "axios";
import { Room, RoomEvent, Track } from "livekit-client";

// Qualité du partage d'écran : 1080p @ 60fps. Pas de preset 60fps fourni par
// LiveKit, donc on définit l'encodage manuellement (bitrate élevé pour rester
// net en mouvement). dynacast/adaptiveStream réduiront automatiquement la
// qualité pour les viewers qui n'affichent le flux qu'en petit.
const SCREEN_SHARE_ENCODING = {
  maxBitrate: 8_000_000, // 8 Mbps
  maxFramerate: 60,
  priority: "high",
};
const SCREEN_SHARE_CAPTURE = {
  audio: true,
  resolution: { width: 1920, height: 1080, frameRate: 60 },
  contentHint: "motion", // fluidité (vidéo/jeu) ; "detail" pour texte/code net
};

// Salon vocal / visio via LiveKit (SFU) : supporte un grand nombre de
// participants (100+) sans saturer les clients, contrairement au mesh.
// Laravel délivre un access token ; le média transite par le serveur LiveKit.

export const currentRoom = ref(null); // { projectId, spaceKey, label, isStage }
export const roomParticipants = ref([]); // [{ identity, name, avatar_url, isLocal, speaking, micOn, camTrack, role, canModerate, handRaised }]
export const localMuted = ref(false);
export const cameraEnabled = ref(false);
export const screenSharing = ref(false);
export const deafened = ref(false);
export const connecting = ref(false);
export const meetingOpen = ref(false);

// ---- Stage / réunion ----
export const myRole = ref("speaker"); // "speaker" | "audience"
export const amModerator = ref(false);
export const canPublishLocal = ref(true);
const handStates = ref({}); // identity -> bool

// ---- Volume (local à chaque utilisateur, persisté) ----
export const masterVolume = ref(1); // 0..1, volume global
export const participantVolumes = ref({}); // identity -> 0..1

export const inRoom = computed(() => currentRoom.value !== null);
export const isStage = computed(() => Boolean(currentRoom.value?.isStage));

let identity = null;
let myName = null;
let myAvatar = null;
let room = null;
const audioEls = new Map(); // trackSid -> { el, identity }
const textEncoder = new TextEncoder();
const textDecoder = new TextDecoder();

const clamp01 = (v) => Math.max(0, Math.min(1, v));

function loadVolumes() {
  try {
    const m = parseFloat(localStorage.getItem("voice.masterVolume"));
    if (!Number.isNaN(m)) masterVolume.value = clamp01(m);
    const pv = JSON.parse(localStorage.getItem("voice.participantVolumes") || "{}");
    if (pv && typeof pv === "object") participantVolumes.value = pv;
  } catch (e) {
    /* noop */
  }
}
loadVolumes();

function applyVolumes() {
  audioEls.forEach(({ el, identity: pid }) => {
    const pv = participantVolumes.value[pid] ?? 1;
    el.volume = clamp01(masterVolume.value * pv);
  });
}

export function setMasterVolume(v) {
  masterVolume.value = clamp01(Number(v));
  applyVolumes();
  try {
    localStorage.setItem("voice.masterVolume", String(masterVolume.value));
  } catch (e) {
    /* noop */
  }
}

export function getParticipantVolume(pid) {
  return participantVolumes.value[String(pid)] ?? 1;
}

export function setParticipantVolume(pid, v) {
  participantVolumes.value = {
    ...participantVolumes.value,
    [String(pid)]: clamp01(Number(v)),
  };
  applyVolumes();
  try {
    localStorage.setItem(
      "voice.participantVolumes",
      JSON.stringify(participantVolumes.value),
    );
  } catch (e) {
    /* noop */
  }
}

export function setVoiceIdentity(user) {
  identity = user?.id != null ? String(user.id) : null;
  myName = user?.name ?? "Moi";
  myAvatar = user?.avatar_url ?? null;
}

function parseMeta(p) {
  try {
    return p.metadata ? JSON.parse(p.metadata) : {};
  } catch (e) {
    return {};
  }
}

function describe(p, isLocal) {
  const meta = parseMeta(p);
  const camPub = p.getTrackPublication?.(Track.Source.Camera);
  const micPub = p.getTrackPublication?.(Track.Source.Microphone);
  const screenPub = p.getTrackPublication?.(Track.Source.ScreenShare);
  return {
    identity: p.identity,
    name: p.name || meta.name || p.identity,
    avatar_url: meta.avatar_url ?? null,
    isLocal,
    speaking: Boolean(p.isSpeaking),
    micOn: isLocal
      ? Boolean(p.isMicrophoneEnabled)
      : Boolean(micPub && !micPub.isMuted),
    camTrack: camPub && !camPub.isMuted ? camPub.track ?? null : null,
    screenTrack: screenPub && !screenPub.isMuted ? screenPub.track ?? null : null,
    role: meta.role ?? "speaker",
    canModerate: Boolean(meta.can_moderate),
    handRaised: Boolean(handStates.value[p.identity]),
  };
}

function updateLocalRole() {
  if (!room) return;
  const meta = parseMeta(room.localParticipant);
  myRole.value = meta.role ?? "speaker";
  amModerator.value = Boolean(meta.can_moderate);
  canPublishLocal.value = Boolean(room.localParticipant.permissions?.canPublish);
}

function rebuild() {
  if (!room) {
    roomParticipants.value = [];
    return;
  }
  const list = [describe(room.localParticipant, true)];
  room.remoteParticipants.forEach((p) => list.push(describe(p, false)));
  roomParticipants.value = list;
}

function bindEvents() {
  const r = room;
  r.on(RoomEvent.TrackSubscribed, (track, _pub, participant) => {
    if (track.kind === "audio") {
      const el = track.attach();
      el.muted = deafened.value;
      const pid = participant?.identity ?? null;
      el.volume = clamp01(masterVolume.value * (participantVolumes.value[pid] ?? 1));
      audioEls.set(track.sid, { el, identity: pid });
    }
    rebuild();
  })
    .on(RoomEvent.TrackUnsubscribed, (track) => {
      if (track.kind === "audio") {
        track.detach().forEach((el) => el.remove());
        audioEls.delete(track.sid);
      }
      rebuild();
    })
    .on(RoomEvent.ParticipantConnected, rebuild)
    .on(RoomEvent.ParticipantDisconnected, (p) => {
      if (p?.identity && handStates.value[p.identity] !== undefined) {
        const next = { ...handStates.value };
        delete next[p.identity];
        handStates.value = next;
      }
      rebuild();
    })
    .on(RoomEvent.TrackMuted, rebuild)
    .on(RoomEvent.TrackUnmuted, rebuild)
    .on(RoomEvent.LocalTrackPublished, rebuild)
    .on(RoomEvent.LocalTrackUnpublished, rebuild)
    .on(RoomEvent.ParticipantMetadataChanged, (_meta, p) => {
      if (p === r.localParticipant) updateLocalRole();
      rebuild();
    })
    .on(RoomEvent.ParticipantPermissionsChanged, (_prev, p) => {
      if (p === r.localParticipant) {
        const couldPublish = canPublishLocal.value;
        updateLocalRole();
        // Promu intervenant : on active le micro automatiquement.
        if (!couldPublish && canPublishLocal.value) {
          localMuted.value = false;
          r.localParticipant.setMicrophoneEnabled(true).catch(() => {});
        }
        // Rétrogradé auditeur : LiveKit retire nos pistes, on remet l'état à plat.
        if (!canPublishLocal.value) {
          cameraEnabled.value = false;
          screenSharing.value = false;
        }
      }
      rebuild();
    })
    .on(RoomEvent.DataReceived, (payload, p) => {
      try {
        const msg = JSON.parse(textDecoder.decode(payload));
        if (msg?.t === "hand" && p?.identity) {
          handStates.value = { ...handStates.value, [p.identity]: Boolean(msg.raised) };
          rebuild();
        }
      } catch (e) {
        /* noop */
      }
    })
    .on(RoomEvent.ActiveSpeakersChanged, rebuild)
    .on(RoomEvent.Disconnected, () => cleanup());
}

function cleanup() {
  audioEls.forEach(({ el }) => el.remove());
  audioEls.clear();
  room = null;
  currentRoom.value = null;
  roomParticipants.value = [];
  localMuted.value = false;
  cameraEnabled.value = false;
  screenSharing.value = false;
  deafened.value = false;
  meetingOpen.value = false;
  myRole.value = "speaker";
  amModerator.value = false;
  canPublishLocal.value = true;
  handStates.value = {};
}

export async function joinRoom(projectSlug, channelId, label, { withVideo = false, openMeetingView = false, projectId = null } = {}) {
  if (inRoom.value || connecting.value) return;
  connecting.value = true;
  try {
    const { data } = await axios.post(
      route("projects.voice.token", [projectSlug, channelId]),
    );

    room = new Room({
      adaptiveStream: true,
      dynacast: true,
      publishDefaults: { screenShareEncoding: SCREEN_SHARE_ENCODING },
    });
    bindEvents();

    await room.connect(data.url, data.token);
    currentRoom.value = {
      projectSlug,
      projectId,
      channelId,
      label,
      isStage: Boolean(data.is_stage),
    };
    window.addEventListener("beforeunload", beaconLeave);
    updateLocalRole();

    // Auditeur de réunion : pas de publication tant qu'il n'est pas promu.
    if (canPublishLocal.value) {
      await room.localParticipant.setMicrophoneEnabled(true);
      if (withVideo) {
        await room.localParticipant.setCameraEnabled(true);
        cameraEnabled.value = true;
      }
    }
    // Salon de réunion : on ouvre la vue scène (visio) sans forcer la caméra.
    if (withVideo || openMeetingView) {
      meetingOpen.value = true;
    }
    rebuild();
  } catch (e) {
    if (e?.name === "NotAllowedError") {
      alert("Accès au micro/caméra refusé.");
    } else if (e?.response?.status === 403) {
      alert("Accès au salon vocal refusé.");
    } else {
      alert("Connexion au salon impossible. Le serveur LiveKit est-il démarré ?");
    }
    await leaveRoom();
  } finally {
    connecting.value = false;
  }
}

function beaconLeave() {
  const r = currentRoom.value;
  if (!r) return;
  try {
    navigator.sendBeacon?.(route("projects.voice.leave", [r.projectSlug, r.channelId]));
  } catch (e) {
    /* noop */
  }
}

export async function leaveRoom() {
  const r = currentRoom.value;
  window.removeEventListener("beforeunload", beaconLeave);
  if (room) {
    try {
      await room.disconnect();
    } catch (e) {
      /* noop */
    }
  }
  if (r) {
    axios.post(route("projects.voice.leave", [r.projectSlug, r.channelId])).catch(() => {});
  }
  cleanup();
}

export async function toggleMute() {
  if (!room || !canPublishLocal.value) return;
  localMuted.value = !localMuted.value;
  await room.localParticipant.setMicrophoneEnabled(!localMuted.value);
  rebuild();
}

export async function toggleCamera() {
  if (!room || !canPublishLocal.value) return;
  cameraEnabled.value = !cameraEnabled.value;
  await room.localParticipant.setCameraEnabled(cameraEnabled.value);
  if (cameraEnabled.value) meetingOpen.value = true;
  rebuild();
}

export async function toggleScreenShare() {
  if (!room || !canPublishLocal.value) return;
  const next = !screenSharing.value;
  try {
    await room.localParticipant.setScreenShareEnabled(
      next,
      SCREEN_SHARE_CAPTURE,
    );
    screenSharing.value = next;
    if (next) meetingOpen.value = true;
  } catch (e) {
    screenSharing.value = false;
  }
  rebuild();
}

export function toggleDeafen() {
  deafened.value = !deafened.value;
  audioEls.forEach(({ el }) => {
    el.muted = deafened.value;
  });
  if (deafened.value && !localMuted.value) toggleMute();
}

// ---- Stage : lever la main, promotion/rétrogradation ----
export async function raiseHand(raised) {
  if (!room) return;
  if (identity != null) {
    handStates.value = { ...handStates.value, [identity]: raised };
    rebuild();
  }
  try {
    await room.localParticipant.publishData(
      textEncoder.encode(JSON.stringify({ t: "hand", raised })),
      { reliable: true },
    );
  } catch (e) {
    /* noop */
  }
}

export async function setParticipantRole(targetIdentity, role) {
  const r = currentRoom.value;
  if (!r) return;
  try {
    const { data } = await axios.post(
      route("projects.voice.set-role", [r.projectSlug, r.channelId]),
      { identity: String(targetIdentity), role },
    );
    if (!data?.ok) {
      alert("Action impossible. Le serveur LiveKit est-il démarré ?");
      return;
    }
    if (role === "speaker") {
      handStates.value = { ...handStates.value, [String(targetIdentity)]: false };
      rebuild();
    }
  } catch (e) {
    alert("Action impossible. Accès refusé ou serveur indisponible.");
  }
}

export function leaveStage() {
  if (identity != null) setParticipantRole(identity, "audience");
}

export function joinStage() {
  if (identity != null) setParticipantRole(identity, "speaker");
}

export function openMeeting() {
  meetingOpen.value = true;
}

export function closeMeeting() {
  meetingOpen.value = false;
}
