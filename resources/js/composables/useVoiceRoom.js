import { computed, ref } from "vue";
import axios from "axios";
import { Room, RoomEvent, Track } from "livekit-client";

// Salon vocal / visio via LiveKit (SFU) : supporte un grand nombre de
// participants (100+) sans saturer les clients, contrairement au mesh.
// Laravel délivre un access token ; le média transite par le serveur LiveKit.

export const currentRoom = ref(null); // { projectId, spaceKey, label }
export const roomParticipants = ref([]); // [{ identity, name, avatar_url, isLocal, speaking, micOn, camTrack }]
export const localMuted = ref(false);
export const cameraEnabled = ref(false);
export const screenSharing = ref(false);
export const deafened = ref(false);
export const connecting = ref(false);
export const meetingOpen = ref(false);

export const inRoom = computed(() => currentRoom.value !== null);

let identity = null;
let myName = null;
let myAvatar = null;
let room = null;
const audioEls = new Map(); // trackSid -> HTMLAudioElement

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
  };
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
  r.on(RoomEvent.TrackSubscribed, (track) => {
    if (track.kind === "audio") {
      const el = track.attach();
      el.muted = deafened.value;
      audioEls.set(track.sid, el);
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
    .on(RoomEvent.ParticipantDisconnected, rebuild)
    .on(RoomEvent.TrackMuted, rebuild)
    .on(RoomEvent.TrackUnmuted, rebuild)
    .on(RoomEvent.LocalTrackPublished, rebuild)
    .on(RoomEvent.LocalTrackUnpublished, rebuild)
    .on(RoomEvent.ParticipantMetadataChanged, rebuild)
    .on(RoomEvent.ActiveSpeakersChanged, rebuild)
    .on(RoomEvent.Disconnected, () => cleanup());
}

function cleanup() {
  audioEls.forEach((el) => el.remove());
  audioEls.clear();
  room = null;
  currentRoom.value = null;
  roomParticipants.value = [];
  localMuted.value = false;
  cameraEnabled.value = false;
  screenSharing.value = false;
  deafened.value = false;
  meetingOpen.value = false;
}

export async function joinRoom(projectSlug, channelId, label, { withVideo = false, projectId = null } = {}) {
  if (inRoom.value || connecting.value) return;
  connecting.value = true;
  try {
    const { data } = await axios.post(
      route("projects.voice.token", [projectSlug, channelId]),
    );

    room = new Room({ adaptiveStream: true, dynacast: true });
    bindEvents();

    await room.connect(data.url, data.token);
    currentRoom.value = { projectSlug, projectId, channelId, label };
    window.addEventListener("beforeunload", beaconLeave);

    await room.localParticipant.setMicrophoneEnabled(true);
    if (withVideo) {
      await room.localParticipant.setCameraEnabled(true);
      cameraEnabled.value = true;
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
  if (!room) return;
  localMuted.value = !localMuted.value;
  await room.localParticipant.setMicrophoneEnabled(!localMuted.value);
  rebuild();
}

export async function toggleCamera() {
  if (!room) return;
  cameraEnabled.value = !cameraEnabled.value;
  await room.localParticipant.setCameraEnabled(cameraEnabled.value);
  if (cameraEnabled.value) meetingOpen.value = true;
  rebuild();
}

export async function toggleScreenShare() {
  if (!room) return;
  const next = !screenSharing.value;
  try {
    await room.localParticipant.setScreenShareEnabled(next, { audio: true });
    screenSharing.value = next;
    if (next) meetingOpen.value = true;
  } catch (e) {
    screenSharing.value = false;
  }
  rebuild();
}

export function toggleDeafen() {
  deafened.value = !deafened.value;
  audioEls.forEach((el) => {
    el.muted = deafened.value;
  });
  if (deafened.value && !localMuted.value) toggleMute();
}

export function openMeeting() {
  meetingOpen.value = true;
}

export function closeMeeting() {
  meetingOpen.value = false;
}
