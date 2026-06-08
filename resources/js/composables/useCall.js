import { computed, ref } from "vue";
import axios from "axios";
import { Room, RoomEvent, Track } from "livekit-client";

// Système d'appel 1:1 via LiveKit (SFU). La couche "sonnerie" (appel entrant /
// accept / decline / hangup) passe par Reverb + les endpoints /calls/*, mais le
// média transite par une room LiveKit dédiée (`call-{id}`). LiveKit gère le
// NAT/TURN : plus de P2P/SDP/ICE à signaler à la main.

export const callStatus = ref("idle"); // idle|outgoing|incoming|connecting|active|ended
export const currentCall = ref(null);
export const callRole = ref(null); // caller|callee
export const localStream = ref(null);
export const remoteStream = ref(null);
export const isMuted = ref(false);
export const cameraOff = ref(false);

export const inCall = computed(() =>
  ["outgoing", "incoming", "connecting", "active"].includes(callStatus.value),
);

let myId = null;
let room = null;
let userChannelBound = false;

function iso() {
  return new Date().toISOString();
}

function log(...args) {
  // eslint-disable-next-line no-console
  console.debug("[call]", iso(), ...args);
}

// Reconstruit le MediaStream local (caméra + micro) à partir des tracks publiées
// par LiveKit, pour l'aperçu local affiché dans CallOverlay.
function rebuildLocalStream() {
  if (!room) return;
  const stream = new MediaStream();
  const lp = room.localParticipant;
  const cam = lp.getTrackPublication?.(Track.Source.Camera)?.track?.mediaStreamTrack;
  const mic = lp.getTrackPublication?.(Track.Source.Microphone)?.track?.mediaStreamTrack;
  if (cam) stream.addTrack(cam);
  if (mic) stream.addTrack(mic);
  localStream.value = stream;
}

async function connectRoom(withVideo) {
  const { data } = await axios.post(route("calls.token", currentCall.value.id));

  room = new Room({ adaptiveStream: true, dynacast: true });
  remoteStream.value = new MediaStream();

  room
    .on(RoomEvent.TrackSubscribed, (track) => {
      if (track.kind === "video" || track.kind === "audio") {
        remoteStream.value.addTrack(track.mediaStreamTrack);
        callStatus.value = "active";
      }
    })
    .on(RoomEvent.TrackUnsubscribed, (track) => {
      try {
        remoteStream.value.removeTrack(track.mediaStreamTrack);
      } catch (e) {
        /* noop */
      }
    })
    .on(RoomEvent.ParticipantDisconnected, () => {
      // L'autre participant a quitté la room → on raccroche de notre côté.
      hangup();
    });

  await room.connect(data.url, data.token);
  await room.localParticipant.setMicrophoneEnabled(true);
  if (withVideo) {
    await room.localParticipant.setCameraEnabled(true);
  }
  rebuildLocalStream();
}

function resetMedia() {
  if (room) {
    try {
      room.removeAllListeners();
      room.disconnect();
    } catch (e) {
      /* noop */
    }
    room = null;
  }
  localStream.value = null;
  remoteStream.value = null;
}

function endLocally(status = "ended") {
  resetMedia();
  callStatus.value = status;
  isMuted.value = false;
  cameraOff.value = false;
  // Repasse à idle après un court instant pour laisser l'UI afficher l'état.
  setTimeout(() => {
    if (callStatus.value === status) {
      callStatus.value = "idle";
      currentCall.value = null;
      callRole.value = null;
    }
  }, 1500);
}

async function onCallStateChanged(event) {
  const call = event?.call;
  if (!call || !currentCall.value || call.id !== currentCall.value.id) return;
  currentCall.value = call;

  if (["declined", "ended", "missed"].includes(call.status)) {
    endLocally("ended");
  }
}

// ---- API publique ----

export function initCall(userId) {
  if (!userId || !window.Echo) return;
  myId = Number(userId);
  if (userChannelBound) return;
  userChannelBound = true;

  // Réutilise le canal privé perso (déjà utilisé par les notifications).
  const channel = window.Echo.private(`App.Models.User.${userId}`);
  channel
    .listen(".IncomingCall", (event) => {
      const call = event?.call;
      if (!call) return;
      // Ignore si déjà en appel.
      if (inCall.value) {
        axios.post(route("calls.decline", call.id)).catch(() => {});
        return;
      }
      currentCall.value = call;
      callRole.value = "callee";
      callStatus.value = "incoming";
    })
    .listen(".CallStateChanged", onCallStateChanged);
}

export async function startCall(callee, { withVideo = false, projectId = null } = {}) {
  if (inCall.value) return;
  try {
    callRole.value = "caller";
    callStatus.value = "outgoing";
    const { data } = await axios.post(route("calls.store"), {
      callee_id: callee.id,
      with_video: withVideo,
      project_id: projectId,
    });
    currentCall.value = data.call;
    // L'appelant rejoint la room et attend l'appelé ; passage à "active" dès
    // qu'une track distante est reçue (TrackSubscribed).
    await connectRoom(withVideo);
  } catch (e) {
    log("startCall error", e);
    endLocally();
    if (e?.response?.status === 403) {
      alert("Vous ne pouvez pas appeler cet utilisateur.");
    } else if (e?.name === "NotAllowedError") {
      alert("Accès au micro/caméra refusé.");
    }
  }
}

export async function acceptCall() {
  if (!currentCall.value || callRole.value !== "callee") return;
  try {
    callStatus.value = "connecting";
    const withVideo = Boolean(currentCall.value.with_video);
    await axios.post(route("calls.accept", currentCall.value.id));
    await connectRoom(withVideo);
  } catch (e) {
    log("acceptCall error", e);
    if (e?.name === "NotAllowedError") {
      alert("Accès au micro/caméra refusé.");
    }
    declineCall();
  }
}

export async function declineCall() {
  if (!currentCall.value) return;
  const id = currentCall.value.id;
  endLocally();
  try {
    await axios.post(route("calls.decline", id));
  } catch (e) {
    /* noop */
  }
}

export async function hangup() {
  if (!currentCall.value) return;
  const id = currentCall.value.id;
  endLocally();
  try {
    await axios.post(route("calls.hangup", id));
  } catch (e) {
    /* noop */
  }
}

export async function toggleMute() {
  if (!room) return;
  isMuted.value = !isMuted.value;
  try {
    await room.localParticipant.setMicrophoneEnabled(!isMuted.value);
  } catch (e) {
    log("toggleMute error", e);
  }
}

export async function toggleCamera() {
  if (!room) return;
  cameraOff.value = !cameraOff.value;
  try {
    await room.localParticipant.setCameraEnabled(!cameraOff.value);
    rebuildLocalStream();
  } catch (e) {
    log("toggleCamera error", e);
  }
}
