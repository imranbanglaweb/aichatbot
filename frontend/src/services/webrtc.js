import Pusher from 'pusher-js';
import api from './api';

/**
 * WebRTC Service for real-time voice communication
 * Uses Pusher for signaling and WebRTC for peer-to-peer audio
 */
class WebRTCService {
  constructor() {
    this.pusher = null;
    this.channel = null;
    this.peerConnection = null;
    this.localStream = null;
    this.remoteStream = null;
    this.callStatus = 'idle'; // idle, calling, ringing, connected, ended
    this.sessionId = null;
    this.callbacks = {
      onCallInitiated: null,
      onCallRinging: null,
      onCallConnected: null,
      onCallEnded: null,
      onRemoteStream: null,
      onError: null,
      onOffer: null,
      onAnswer: null,
      onIceCandidate: null,
    };
    
    // ICE servers configuration
    this.iceServers = [
      { urls: 'stun:stun.l.google.com:19302' },
      { urls: 'stun:stun1.l.google.com:19302' },
    ];
  }

  /**
   * Initialize Pusher and get configuration from server
   */
  async initialize() {
    try {
      // Get Pusher configuration from backend
      const config = await api.request('/webrtc/config');
      
      this.pusher = new Pusher(config.pusher_key, {
        cluster: config.pusher_cluster,
        forceTLS: true,
      });

      console.log('WebRTC Service initialized with Pusher');
      return config;
    } catch (error) {
      console.error('Failed to initialize WebRTC service:', error);
      throw error;
    }
  }

  /**
   * Set callbacks for various call events
   */
  on(event, callback) {
    if (this.callbacks.hasOwnProperty(event)) {
      this.callbacks[event] = callback;
    }
  }

  /**
   * Set custom ICE servers
   */
  setIceServers(servers) {
    this.iceServers = servers;
  }

  /**
   * Subscribe to a voice call channel
   */
  subscribeToCall(sessionId) {
    if (this.channel) {
      this.unsubscribeFromCall();
    }

    this.sessionId = sessionId;
    this.channel = this.pusher.subscribe(`voice-call.${sessionId}`);

    // Bind to all voice call events
    this.channel.bind('voice-call.offer', (data) => {
      console.log('Received offer:', data);
      if (this.callbacks.onOffer) {
        this.callbacks.onOffer(data);
      }
    });

    this.channel.bind('voice-call.answer', (data) => {
      console.log('Received answer:', data);
      if (this.callbacks.onAnswer) {
        this.callbacks.onAnswer(data);
      }
    });

    this.channel.bind('voice-call.ice-candidate', (data) => {
      console.log('Received ICE candidate:', data);
      if (this.callbacks.onIceCandidate) {
        this.callbacks.onIceCandidate(data);
      }
    });

    this.channel.bind('voice-call.ringing', (data) => {
      console.log('Call ringing:', data);
      this.callStatus = 'ringing';
      if (this.callbacks.onCallRinging) {
        this.callbacks.onCallRinging(data);
      }
    });

    this.channel.bind('voice-call.ended', (data) => {
      console.log('Call ended:', data);
      this.callStatus = 'ended';
      this.cleanup();
      if (this.callbacks.onCallEnded) {
        this.callbacks.onCallEnded(data);
      }
    });

    console.log(`Subscribed to voice call channel: voice-call.${sessionId}`);
    return this.channel;
  }

  /**
   * Unsubscribe from the current call channel
   */
  unsubscribeFromCall() {
    if (this.channel) {
      this.pusher.unsubscribe(`voice-call.${this.sessionId}`);
      this.channel = null;
    }
  }

  /**
   * Authenticate with Pusher for private channel
   */
  async authenticatePusher(channelName, socketId) {
    const response = await api.request('/webrtc/auth/pusher', {
      method: 'POST',
      body: JSON.stringify({
        channel_name: channelName,
        socket_id: socketId,
      }),
    });
    return response;
  }

  /**
   * Create a new peer connection
   */
  createPeerConnection() {
    this.peerConnection = new RTCPeerConnection({
      iceServers: this.iceServers,
    });

    // Handle ICE candidates
    this.peerConnection.onicecandidate = (event) => {
      if (event.candidate) {
        this.sendIceCandidate(event.candidate);
      }
    };

    // Handle remote stream
    this.peerConnection.ontrack = (event) => {
      console.log('Received remote stream');
      this.remoteStream = event.streams[0];
      if (this.callbacks.onRemoteStream) {
        this.callbacks.onRemoteStream(event.streams[0]);
      }
    };

    // Handle connection state changes
    this.peerConnection.onconnectionstatechange = () => {
      console.log('Connection state:', this.peerConnection.connectionState);
      
      if (this.peerConnection.connectionState === 'connected') {
        this.callStatus = 'connected';
        if (this.callbacks.onCallConnected) {
          this.callbacks.onCallConnected();
        }
      } else if (this.peerConnection.connectionState === 'disconnected' || 
                 this.peerConnection.connectionState === 'failed') {
        this.callStatus = 'ended';
        this.cleanup();
      }
    };

    // Add local tracks if available
    if (this.localStream) {
      this.localStream.getTracks().forEach(track => {
        this.peerConnection.addTrack(track, this.localStream);
      });
    }

    return this.peerConnection;
  }

  /**
   * Start local audio stream
   */
  async startLocalStream() {
    try {
      this.localStream = await navigator.mediaDevices.getUserMedia({
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true,
        },
        video: false,
      });
      console.log('Local stream started');
      return this.localStream;
    } catch (error) {
      console.error('Failed to get local stream:', error);
      if (this.callbacks.onError) {
        this.callbacks.onError('Failed to access microphone. Please check permissions.');
      }
      throw error;
    }
  }

  /**
   * Initiate a new voice call
   */
  async initiateCall(calleeId, callType = 'audio') {
    try {
      this.callStatus = 'calling';

      // Start local audio
      await this.startLocalStream();

      // Create call session on server
      const response = await api.request('/webrtc/call/initiate', {
        method: 'POST',
        body: JSON.stringify({
          callee_id: calleeId,
          call_type: callType,
        }),
      });

      this.sessionId = response.session_id;
      
      // Subscribe to the call channel
      this.subscribeToCall(this.sessionId);

      // Create peer connection
      this.createPeerConnection();

      // Create offer
      const offer = await this.peerConnection.createOffer();
      await this.peerConnection.setLocalDescription(offer);

      // Send offer to server
      await api.request('/webrtc/call/offer', {
        method: 'POST',
        body: JSON.stringify({
          session_id: this.sessionId,
          offer: JSON.stringify(offer),
          call_type: callType,
        }),
      });

      if (this.callbacks.onCallInitiated) {
        this.callbacks.onCallInitiated({
          sessionId: this.sessionId,
          calleeId,
          callType,
        });
      }

      return { sessionId: this.sessionId, ...response };
    } catch (error) {
      console.error('Failed to initiate call:', error);
      this.callStatus = 'idle';
      throw error;
    }
  }

  /**
   * Handle incoming offer
   */
  async handleOffer(offerData) {
    try {
      this.callStatus = 'calling';
      this.sessionId = offerData.session_id;

      // Start local audio
      await this.startLocalStream();

      // Create peer connection
      this.createPeerConnection();

      // Set remote description (offer)
      const offer = JSON.parse(offerData.offer);
      await this.peerConnection.setRemoteDescription(new RTCSessionDescription(offer));

      // Create answer
      const answer = await this.peerConnection.createAnswer();
      await this.peerConnection.setLocalDescription(answer);

      // Send ringing status
      await api.request('/webrtc/call/ringing', {
        method: 'POST',
        body: JSON.stringify({
          session_id: this.sessionId,
          caller_id: offerData.caller_id,
        }),
      });

      // Send answer to server
      await api.request('/webrtc/call/answer', {
        method: 'POST',
        body: JSON.stringify({
          session_id: this.sessionId,
          answer: JSON.stringify(answer),
        }),
      });

      return true;
    } catch (error) {
      console.error('Failed to handle offer:', error);
      throw error;
    }
  }

  /**
   * Handle incoming answer
   */
  async handleAnswer(answerData) {
    try {
      const answer = JSON.parse(answerData.answer);
      await this.peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
      console.log('Answer processed successfully');
      return true;
    } catch (error) {
      console.error('Failed to handle answer:', error);
      throw error;
    }
  }

  /**
   * Handle incoming ICE candidate
   */
  async handleIceCandidate(candidateData) {
    try {
      if (this.peerConnection && candidateData.candidate) {
        await this.peerConnection.addIceCandidate(new RTCIceCandidate(candidateData.candidate));
        console.log('ICE candidate added');
      }
      return true;
    } catch (error) {
      console.error('Failed to handle ICE candidate:', error);
      throw error;
    }
  }

  /**
   * Send ICE candidate to server
   */
  async sendIceCandidate(candidate) {
    try {
      await api.request('/webrtc/call/ice-candidate', {
        method: 'POST',
        body: JSON.stringify({
          session_id: this.sessionId,
          candidate: {
            candidate: candidate.candidate,
            sdpMid: candidate.sdpMid,
            sdpMLineIndex: candidate.sdpMLineIndex,
          },
        }),
      });
    } catch (error) {
      console.error('Failed to send ICE candidate:', error);
    }
  }

  /**
   * End the current call
   */
  async endCall() {
    try {
      const duration = this.calculateCallDuration();
      
      await api.request('/webrtc/call/end', {
        method: 'POST',
        body: JSON.stringify({
          session_id: this.sessionId,
          duration,
        }),
      });

      this.cleanup();
      this.callStatus = 'ended';
      
      if (this.callbacks.onCallEnded) {
        this.callbacks.onCallEnded({ duration });
      }

      return true;
    } catch (error) {
      console.error('Failed to end call:', error);
      // Still cleanup locally even if server request fails
      this.cleanup();
      throw error;
    }
  }

  /**
   * Calculate call duration
   */
  calculateCallDuration() {
    if (this.callStartTime) {
      return Math.floor((Date.now() - this.callStartTime) / 1000);
    }
    return 0;
  }

  /**
   * Mute/unmute local audio
   */
  toggleMute(mute) {
    if (this.localStream) {
      this.localStream.getAudioTracks().forEach(track => {
        track.enabled = !mute;
      });
    }
  }

  /**
   * Clean up resources
   */
  cleanup() {
    // Stop local stream
    if (this.localStream) {
      this.localStream.getTracks().forEach(track => track.stop());
      this.localStream = null;
    }

    // Close peer connection
    if (this.peerConnection) {
      this.peerConnection.close();
      this.peerConnection = null;
    }

    // Unsubscribe from channel
    this.unsubscribeFromCall();

    // Reset state
    this.remoteStream = null;
    this.callStartTime = null;
    this.callStatus = 'idle';
  }

  /**
   * Get current call status
   */
  getCallStatus() {
    return this.callStatus;
  }

  /**
   * Get local stream
   */
  getLocalStream() {
    return this.localStream;
  }

  /**
   * Get remote stream
   */
  getRemoteStream() {
    return this.remoteStream;
  }
}

export default new WebRTCService();
