declare module 'recordrtc' {
  export interface RecordRTCOptions {
    type?: string;
    mimeType?: string;
    recorderType?: any;
    disableLogs?: boolean;
    numberOfAudioChannels?: number;
    bufferSize?: number;
    sampleRate?: number;
    desiredSampRate?: number;
    bitsPerSecond?: number;
    audioBitsPerSecond?: number;
    videoBitsPerSecond?: number;
    timeSlice?: number;
    ondataavailable?: (blob: Blob) => void;
  }

  export class StereoAudioRecorder {
    constructor(stream: MediaStream, config?: any);
  }

  export class MediaStreamRecorder {
    constructor(stream: MediaStream, config?: any);
  }

  export default class RecordRTC {
    static StereoAudioRecorder: typeof StereoAudioRecorder;
    static MediaStreamRecorder: typeof MediaStreamRecorder;

    constructor(stream: MediaStream, options?: RecordRTCOptions);
    startRecording(): void;
    stopRecording(callback?: () => void): void;
    pauseRecording(): void;
    resumeRecording(): void;
    getBlob(): Blob;
    toURL(): string;
    save(fileName?: string): void;
    destroy(): void;
    getState(): string;
    reset(): void;
  }
}
