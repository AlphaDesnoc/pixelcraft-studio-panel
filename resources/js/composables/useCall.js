import { computed, ref } from "vue";
import axios from "axios";

// État global partagé (singleton) du système d'appel 1:1 WebRTC.
// Signalisation via Reverb (canaux privés) + endpoints /calls/*.

const STUN = [{ urls: "stun:stun.l.google.com:19302" }];

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
let pc = null;
let callChannel = null;
let pendingIce = [];
let userChannelBound = false;
let offerStarted = false;

function iso() {
  return new Date().toISOString();
}

function log(...args) {
  // eslint-disable-next-line no-console
  console.debug("[call]", iso(), ...args);
}

function resetMedia() {
  if (pc) {
    try {
      pc.ontrack = null;
      pc.onicecandidate = null;
      pc.onconnectionstatechange = null;
      pc.close();
    } catch (e) {
      /* noop */
    }
    pc = null;
  }
  if (localStream.value) {
    localStream.value.getTracks().forEach((t) => t.stop());
  }
  localStream.value = null;
  remoteStream.value = null;
  pendingIce = [];
  offerStarted = false;
  if (callChannel && currentCall.value) {
    window.Echo?.leave(`call.${currentCall.value.id}`);
  }
  callChannel = null;
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

async function sendSignal(kind, data) {
  if (!currentCall.value) return;
  try {
    await axios.post(route("calls.signal", currentCall.value.id), { kind, data });
  } catch (e) {
    log("signal error", kind, e);
  }
}

async function createPeer(withVideo) {
  pc = new RTCPeerConnection({ iceServers: STUN });

  localStream.value = await navigator.mediaDevices.getUserMedia({
    audio: true,
    video: withVideo,
  });
  localStream.value.getTracks().forEach((track) => {
    pc.addTrack(track, localStream.value);
  });

  remoteStream.value = new MediaStream();
  pc.ontrack = (event) => {
    event.streams[0]?.getTracks().forEach((t) => remoteStream.value.addTrack(t));
    callStatus.value = "active";
  };

  pc.onicecandidate = (event) => {
    if (event.candidate) sendSignal("ice", { candidate: event.candidate });
  };

  pc.onconnectionstatechange = () => {
    log("pc state", pc?.connectionState);
    if (pc?.connectionState === "connected") callStatus.value = "active";
    if (["failed", "disconnected", "closed"].includes(pc?.connectionState)) {
      // laisse l'autre côté gérer via CallStateChanged ; sinon coupe.
    }
  };
}

function subscribeCallChannel(callId) {
  // On attend la confirmation d'abonnement avant de poursuivre. Sinon l'appelé
  // peut poster "accept" puis recevoir l'offre WebRTC AVANT que Reverb n'ait
  // validé son abonnement au canal d'appel : l'offre est alors perdue et la
  // connexion ne s'établit jamais ("impossible de rejoindre l'appel").
  return new Promise((resolve) => {
    let done = false;
    const finish = () => {
      if (done) return;
      done = true;
      resolve();
    };

    callChannel = window.Echo.private(`call.${callId}`);
    callChannel
      .listen(".CallSignal", onCallSignal)
      .listen(".CallStateChanged", onCallStateChanged)
      .subscribed(finish);

    // Filet de sécurité si l'événement "subscribed" n'arrive pas (timeout court).
    setTimeout(finish, 2000);
  });
}

async function onCallSignal(event) {
  if (!event || event.from_id === myId || !pc) return;
  const { kind, data } = event;

  if (kind === "offer") {
    await pc.setRemoteDescription(new RTCSessionDescription(data.sdp));
    await flushIce();
    const answer = await pc.createAnswer();
    await pc.setLocalDescription(answer);
    sendSignal("answer", { sdp: answer });
  } else if (kind === "answer") {
    await pc.setRemoteDescription(new RTCSessionDescription(data.sdp));
    await flushIce();
  } else if (kind === "ice" && data.candidate) {
    try {
      if (pc.remoteDescription && pc.remoteDescription.type) {
        await pc.addIceCandidate(new RTCIceCandidate(data.candidate));
      } else {
        pendingIce.push(data.candidate);
      }
    } catch (e) {
      log("addIceCandidate error", e);
    }
  }
}

async function flushIce() {
  while (pendingIce.length) {
    const cand = pendingIce.shift();
    try {
      await pc.addIceCandidate(new RTCIceCandidate(cand));
    } catch (e) {
      log("flushIce error", e);
    }
  }
}

async function onCallStateChanged(event) {
  const call = event?.call;
  if (!call || !currentCall.value || call.id !== currentCall.value.id) return;
  currentCall.value = call;

  if (call.status === "accepted") {
    // Côté appelant : l'appelé a accepté → on crée l'offre (une seule fois,
    // l'événement pouvant arriver sur le canal perso ET le canal d'appel).
    if (callRole.value === "caller" && pc && !offerStarted) {
      offerStarted = true;
      callStatus.value = "connecting";
      const offer = await pc.createOffer();
      await pc.setLocalDescription(offer);
      sendSignal("offer", { sdp: offer });
    }
  } else if (["declined", "ended", "missed"].includes(call.status)) {
    endLocally(call.status === "declined" ? "ended" : "ended");
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
    await createPeer(withVideo);
    await subscribeCallChannel(data.call.id);
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
    await createPeer(withVideo);
    await subscribeCallChannel(currentCall.value.id);
    await axios.post(route("calls.accept", currentCall.value.id));
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

export function toggleMute() {
  if (!localStream.value) return;
  isMuted.value = !isMuted.value;
  localStream.value.getAudioTracks().forEach((t) => {
    t.enabled = !isMuted.value;
  });
}

export function toggleCamera() {
  if (!localStream.value) return;
  cameraOff.value = !cameraOff.value;
  localStream.value.getVideoTracks().forEach((t) => {
    t.enabled = !cameraOff.value;
  });
}
