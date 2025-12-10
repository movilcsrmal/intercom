// app.js - Frontend logic: presencia, grid UI, señalización y WebRTC
// - Polling de presencia: cada 5s se hace ping al servidor para mantener presencia
// - Polling de señalización: cada 1s se solicita mensajes en api/signaling_receive.php
// - Llamadas: caller envía offer con su audio; receptor responde con answer (recibe solo).
// - Para que el receptor envíe audio de vuelta, pulsa su propio botón y se realiza renegociación.
// - SDP se ajusta para OPUS según la sala (mono/stereo y bitrate).
(function(){
  const serverBase = window.APP && window.APP.serverBase ? window.APP.serverBase : 'api';
  const myIp = window.APP && window.APP.myIp ? window.APP.myIp : '';
  const room = window.APP && window.APP.room ? window.APP.room : 1;
  const myIndex = window.APP && typeof window.APP.myIndex !== 'undefined' ? window.APP.myIndex : -1;
  const grid = document.getElementById('grid');
  const remoteAudio = document.getElementById('remoteAudio');
  const presenceInterval = 5000;
  const signalingPoll = 1000;

  let peers = {}; // key: remoteIp -> {pc, state:'idle'|'calling'|'incall'}
  let localStream = null;
  let audioOutputDeviceId = localStorage.getItem('audioOutput') || '';
  let audioInputDeviceId = localStorage.getItem('audioInput') || '';

  // ---------- UI / Presence ----------
  function updateGridFromPresence(list) {
    const buttons = grid.querySelectorAll('.grid-btn');
    buttons.forEach(btn => {
      const ip = btn.dataset.ip || '';
      if (!ip) {
        btn.classList.remove('btn-online','btn-calling');
        btn.classList.add('btn-empty');
      } else {
        btn.classList.remove('btn-empty','btn-calling');
        btn.classList.add('btn-offline');
      }
    });
    (list || []).forEach(p => {
      if (parseInt(p.room) !== parseInt(room)) return;
      const btn = findButtonByIp(p.ip);
      if (btn) {
        btn.classList.remove('btn-offline','btn-empty');
        btn.classList.add('btn-online');
      }
    });
  }

  async function fetchPresence() {
    try {
      const r = await fetch(`${serverBase}/presence_list.php?room=${room}`);
      const arr = await r.json();
      updateGridFromPresence(arr);
    } catch(e){
      // ignore
    }
  }

  async function sendPresencePing(){
    try {
      const nameEl = document.querySelector('.yourinfo strong');
      const name = nameEl ? nameEl.textContent : '';
      await fetch(`${serverBase}/presence_ping.php`, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: `room=${encodeURIComponent(room)}&name=${encodeURIComponent(name)}`
      });
    } catch(e){}
  }

  // ---------- Signaling polling ----------
  async function pollSignaling(){
    try {
      const r = await fetch(`${serverBase}/signaling_receive.php`);
      const arr = await r.json();
      if (Array.isArray(arr) && arr.length) {
        arr.forEach(handleMessage);
      }
    } catch(e){}
  }

  async function sendSignal(targetIp, message) {
    try {
      await fetch(`${serverBase}/signaling_send.php`, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({target_ip: targetIp, message: message})
      });
    } catch(e){}
  }

  // ---------- PeerConnection helpers ----------
  function createPeerConnection(remoteIp) {
    const pc = new RTCPeerConnection({iceServers:[]});
    pc.onicecandidate = (evt) => {
      if (evt.candidate) {
        sendSignal(remoteIp, {type:'ice', candidate: evt.candidate});
      }
    };
    pc.ontrack = (evt) => {
      remoteAudio.srcObject = evt.streams[0];
      // try set sinkId if user selected output
      if (audioOutputDeviceId && typeof remoteAudio.setSinkId === 'function') {
        remoteAudio.setSinkId(audioOutputDeviceId).catch(()=>{});
      }
    };
    pc.onconnectionstatechange = () => {
      if (['disconnected','failed','closed'].includes(pc.connectionState)) {
        hangupPeer(remoteIp);
      }
    };
    return pc;
  }

  async function handleMessage(msg) {
    if (!msg || !msg.type || !msg.from) return;
    const from = msg.from;
    if (!peers[from]) peers[from] = {state:'idle', pc:null};
    const p = peers[from];

    try {
      if (msg.type === 'offer') {
        // Receiver: set remote and answer (receive-only initially)
        if (!p.pc) p.pc = createPeerConnection(from);
        await p.pc.setRemoteDescription(new RTCSessionDescription(msg.sdp));
        const answer = await p.pc.createAnswer();
        await p.pc.setLocalDescription(answer);
        await sendSignal(from, {type:'answer', sdp: p.pc.localDescription});
        p.state = 'incall';
        markButtonCalling(from);
      } else if (msg.type === 'answer') {
        if (p.pc && msg.sdp) {
          await p.pc.setRemoteDescription(new RTCSessionDescription(msg.sdp));
          p.state = 'incall';
          markButtonIncall(from);
        }
      } else if (msg.type === 'ice') {
        if (p.pc && msg.candidate) {
          try { await p.pc.addIceCandidate(new RTCIceCandidate(msg.candidate)); } catch(e){}
        }
      } else if (msg.type === 'reneg-offer') {
        // remote wants to send audio to us (renegotiation offer)
        if (!p.pc) p.pc = createPeerConnection(from);
        await p.pc.setRemoteDescription(new RTCSessionDescription(msg.sdp));
        // create answer (we will receive their send)
        const answer = await p.pc.createAnswer();
        await p.pc.setLocalDescription(answer);
        await sendSignal(from, {type:'reneg-answer', sdp: p.pc.localDescription});
      } else if (msg.type === 'reneg-answer') {
        if (p.pc && msg.sdp) {
          await p.pc.setRemoteDescription(new RTCSessionDescription(msg.sdp));
        }
      } else if (msg.type === 'end') {
        hangupPeer(from);
      }
    } catch (e) {
      // ignore per-user errors, but ensure cleanup if necessary
      console.error('handleMessage error', e);
    }
  }

  function markButtonCalling(remoteIp) {
    const btn = findButtonByIp(remoteIp);
    if (btn) {
      btn.classList.remove('btn-online','btn-offline','btn-empty');
      btn.classList.add('btn-calling');
    }
  }
  function markButtonIncall(remoteIp) {
    const btn = findButtonByIp(remoteIp);
    if (btn) {
      btn.classList.remove('btn-online','btn-offline','btn-empty');
      btn.classList.add('btn-calling');
    }
  }

  function findButtonByIp(ip) {
    if (!ip) return null;
    return grid.querySelector(`.grid-btn[data-ip="${ip}"]`);
  }

  function hangupPeer(remoteIp) {
    const p = peers[remoteIp];
    if (p && p.pc) {
      try { p.pc.close(); } catch(e){}
    }
    delete peers[remoteIp];
    const btn = findButtonByIp(remoteIp);
    if (btn) {
      btn.classList.remove('btn-calling');
      // if still present, show online
      btn.classList.add('btn-online');
    }
    // stop local stream if exists and not used by other peers
    if (localStream) {
      try { localStream.getTracks().forEach(t=>t.stop()); } catch(e){}
      localStream = null;
    }
  }

  // ---------- Button interactions ----------
  // Click on other user's button: toggle call (brief press)
  if (grid) {
    grid.addEventListener('click', async function(ev) {
      const btn = ev.target.closest('.grid-btn');
      if (!btn) return;
      const targetIp = btn.dataset.ip || '';
      if (!targetIp) return;
      if (targetIp === myIp) return; // cannot call self

      const p = peers[targetIp] || {state:'idle', pc:null};
      if (p.state === 'idle') {
        // start call as caller: obtain mic and send offer
        try {
          localStream = await navigator.mediaDevices.getUserMedia({ audio: audioInputDeviceId ? {deviceId:{exact: audioInputDeviceId}} : true });
        } catch(e){ alert('No se pudo acceder al micrófono'); return; }
        const pc = createPeerConnection(targetIp);
        localStream.getTracks().forEach(t => pc.addTrack(t, localStream));
        peers[targetIp] = {pc: pc, state:'calling'};
        const offer = await pc.createOffer();
        let sdp = offer.sdp;
        sdp = adjustOpusOptions(sdp, room);
        await pc.setLocalDescription({type:offer.type, sdp:sdp});
        await sendSignal(targetIp, {type:'offer', sdp: pc.localDescription});
        markButtonCalling(targetIp);
      } else {
        // toggle off -> hang up
        await sendSignal(targetIp, {type:'end'});
        hangupPeer(targetIp);
      }
    });

    // Long press (>2s) hold-to-talk: start call while pressed, end on mouseup
    let longPressTimer = null;
    let longPressTarget = null;
    grid.addEventListener('mousedown', function(ev){
      const btn = ev.target.closest('.grid-btn');
      if (!btn) return;
      const targetIp = btn.dataset.ip || '';
      if (!targetIp || targetIp === myIp) return;
      longPressTimer = setTimeout(async () => {
        longPressTarget = targetIp;
        try {
          localStream = await navigator.mediaDevices.getUserMedia({ audio: audioInputDeviceId ? {deviceId:{exact: audioInputDeviceId}} : true });
        } catch(e){ longPressTarget = null; return; }
        const pc = createPeerConnection(targetIp);
        localStream.getTracks().forEach(t => pc.addTrack(t, localStream));
        peers[targetIp] = {pc: pc, state:'calling'};
        const offer = await pc.createOffer();
        let sdp = offer.sdp;
        sdp = adjustOpusOptions(sdp, room);
        await pc.setLocalDescription({type:offer.type, sdp:sdp});
        await sendSignal(targetIp, {type:'offer', sdp: pc.localDescription});
        markButtonCalling(targetIp);
      }, 2000);
    });
    document.addEventListener('mouseup', function(ev){
      if (longPressTimer) { clearTimeout(longPressTimer); longPressTimer = null; }
      if (longPressTarget) {
        const tp = longPressTarget;
        longPressTarget = null;
        if (peers[tp]) {
          sendSignal(tp, {type:'end'});
          hangupPeer(tp);
        }
      }
    });
  }

  // Clicking your own button sends audio back to any caller (renegotiation)
  function attachMyButtonHandler(){
    const myBtn = grid ? grid.querySelector(`.grid-btn[data-ip="${myIp}"]`) : null;
    if (!myBtn) return;
    myBtn.addEventListener('click', async function(){
      // find a peer that has an active pc (incoming call)
      for (const remoteIp in peers) {
        const p = peers[remoteIp];
        if (!p || !p.pc) continue;
        // add local track and renegotiate
        try {
          localStream = await navigator.mediaDevices.getUserMedia({ audio: audioInputDeviceId ? {deviceId:{exact: audioInputDeviceId}} : true });
        } catch(e){ alert('No se pudo acceder al micrófono'); return; }
        localStream.getTracks().forEach(t => p.pc.addTrack(t, localStream));
        const offer = await p.pc.createOffer();
        let sdp = offer.sdp;
        sdp = adjustOpusOptions(sdp, room);
        await p.pc.setLocalDescription({type:offer.type, sdp:sdp});
        await sendSignal(remoteIp, {type:'reneg-offer', sdp: p.pc.localDescription});
        p.state = 'incall';
        markButtonIncall(remoteIp);
        break;
      }
    });
  }

  // ---------- SDP adjustments for OPUS ----------
  function adjustOpusOptions(sdp, roomNum) {
    const lines = sdp.split('\r\n');
    let opusPayload = null;
    for (let i=0;i<lines.length;i++){
      const m = lines[i].match(/^a=rtpmap:(\d+)\s+opus\/48000/i);
      if (m) { opusPayload = m[1]; break; }
    }
    if (!opusPayload) return sdp;
    let params = [];
    if (parseInt(roomNum) === 4) {
      params.push('stereo=1');
      params.push('maxaveragebitrate=256000');
    } else {
      params.push('stereo=0');
      params.push('maxaveragebitrate=128000');
    }
    const fmtpLine = 'a=fmtp:' + opusPayload + ' ' + params.join(';');
    const fmtpIndex = lines.findIndex(l => l.startsWith('a=fmtp:' + opusPayload));
    if (fmtpIndex >= 0) lines[fmtpIndex] = fmtpLine;
    else {
      const mIndex = lines.findIndex(l => l.startsWith('m=audio'));
      if (mIndex >= 0) lines.splice(mIndex+1, 0, fmtpLine);
      else lines.push(fmtpLine);
    }
    return lines.join('\r\n');
  }

  // ---------- Audio device modal ----------
  const speakerBtn = document.getElementById('speakerBtn');
  const modal = document.getElementById('modal-audio');
  const audioInput = document.getElementById('audioInput');
  const audioOutput = document.getElementById('audioOutput');
  const saveAudio = document.getElementById('saveAudio');
  const closeAudio = document.getElementById('closeAudio');

  async function openAudioModal() {
    if (!modal) return;
    modal.classList.remove('hidden');
    try {
      await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch(e){}
    let devices = [];
    try { devices = await navigator.mediaDevices.enumerateDevices(); } catch(e){}
    if (audioInput) audioInput.innerHTML = '';
    if (audioOutput) audioOutput.innerHTML = '';
    devices.forEach(d=>{
      if (d.kind === 'audioinput' && audioInput) {
        const o = document.createElement('option'); o.value = d.deviceId; o.textContent = d.label || ('Mic');
        audioInput.appendChild(o);
      } else if (d.kind === 'audiooutput' && audioOutput) {
        const o = document.createElement('option'); o.value = d.deviceId; o.textContent = d.label || ('Altavoz');
        audioOutput.appendChild(o);
      }
    });
    if (audioInput && audioInputDeviceId) audioInput.value = audioInputDeviceId;
    if (audioOutput && audioOutputDeviceId) audioOutput.value = audioOutputDeviceId;
  }

  if (speakerBtn) speakerBtn.addEventListener('click', openAudioModal);
  if (closeAudio) closeAudio.addEventListener('click', ()=>{ if (modal) modal.classList.add('hidden'); });
  if (saveAudio) saveAudio.addEventListener('click', ()=>{
    if (audioInput) localStorage.setItem('audioInput', audioInput.value);
    if (audioOutput) localStorage.setItem('audioOutput', audioOutput.value);
    audioInputDeviceId = audioInput ? audioInput.value : '';
    audioOutputDeviceId = audioOutput ? audioOutput.value : '';
    if (audioOutputDeviceId && typeof remoteAudio.setSinkId === 'function') {
      remoteAudio.setSinkId(audioOutputDeviceId).catch(()=>{});
    }
    if (modal) modal.classList.add('hidden');
  });

  // ---------- Init ----------
  function init(){
    fetchPresence();
    sendPresencePing();
    setInterval(fetchPresence, presenceInterval);
    setInterval(sendPresencePing, presenceInterval);
    setInterval(pollSignaling, signalingPoll);
    attachMyButtonHandler();
    // apply sink if known
    if (audioOutputDeviceId && remoteAudio && typeof remoteAudio.setSinkId === 'function') {
      remoteAudio.setSinkId(audioOutputDeviceId).catch(()=>{});
    }
  }

  init();

})();