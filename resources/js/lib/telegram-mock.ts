/**
 * Telegram WebApp SDK Mock for Local Development
 * 
 * This mock provides a complete implementation of the Telegram WebApp API
 * for local development and testing purposes.
 */

export interface TelegramUser {
  id: number;
  first_name: string;
  last_name?: string;
  username?: string;
  language_code?: string;
  is_premium?: boolean;
  photo_url?: string;
}

export interface TelegramWebApp {
  initData: string;
  initDataUnsafe: {
    query_id?: string;
    user?: TelegramUser;
    receiver?: TelegramUser;
    chat?: any;
    start_param?: string;
    auth_date: number;
    hash: string;
  };
  version: string;
  platform: string;
  colorScheme: 'light' | 'dark';
  themeParams: {
    bg_color?: string;
    text_color?: string;
    hint_color?: string;
    link_color?: string;
    button_color?: string;
    button_text_color?: string;
    secondary_bg_color?: string;
  };
  isExpanded: boolean;
  viewportHeight: number;
  viewportStableHeight: number;
  headerColor: string;
  backgroundColor: string;
  isClosingConfirmationEnabled: boolean;
  
  // Methods
  ready(): void;
  close(): void;
  expand(): void;
  enableClosingConfirmation(): void;
  disableClosingConfirmation(): void;
  onEvent(eventType: string, eventHandler: Function): void;
  offEvent(eventType: string, eventHandler: Function): void;
  sendData(data: string): void;
  showPopup(params: {
    title?: string;
    message: string;
    buttons?: Array<{
      id?: string;
      type?: 'default' | 'ok' | 'close' | 'cancel' | 'destructive';
      text: string;
    }>;
  }, callback?: (buttonId: string) => void): void;
  showAlert(message: string, callback?: () => void): void;
  showConfirm(message: string, callback?: (confirmed: boolean) => void): void;
  showScanQrPopup(params: {
    text?: string;
  }, callback?: (data: string) => boolean): void;
  closeScanQrPopup(): void;
  readTextFromClipboard(callback?: (data: string) => void): void;
  
  // Main Button
  MainButton: {
    text: string;
    color: string;
    textColor: string;
    isVisible: boolean;
    isActive: boolean;
    isProgressVisible: boolean;
    setText(text: string): void;
    onClick(callback: () => void): void;
    offClick(callback: () => void): void;
    show(): void;
    hide(): void;
    enable(): void;
    disable(): void;
    showProgress(leaveActive?: boolean): void;
    hideProgress(): void;
    setParams(params: {
      text?: string;
      color?: string;
      text_color?: string;
      is_active?: boolean;
      is_visible?: boolean;
    }): void;
  };
  
  // Back Button
  BackButton: {
    isVisible: boolean;
    onClick(callback: () => void): void;
    offClick(callback: () => void): void;
    show(): void;
    hide(): void;
  };
  
  // Settings Button
  SettingsButton: {
    isVisible: boolean;
    onClick(callback: () => void): void;
    offClick(callback: () => void): void;
    show(): void;
    hide(): void;
  };
  
  // Haptic Feedback
  HapticFeedback: {
    impactOccurred(style: 'light' | 'medium' | 'heavy' | 'rigid' | 'soft'): void;
    notificationOccurred(type: 'error' | 'success' | 'warning'): void;
    selectionChanged(): void;
  };
  
  // Cloud Storage
  CloudStorage: {
    setItem(key: string, value: string, callback?: (error: string | null, success: boolean) => void): void;
    getItem(key: string, callback: (error: string | null, value: string) => void): void;
    getItems(keys: string[], callback: (error: string | null, values: {[key: string]: string}) => void): void;
    removeItem(key: string, callback?: (error: string | null, success: boolean) => void): void;
    removeItems(keys: string[], callback?: (error: string | null, success: boolean) => void): void;
    getKeys(callback: (error: string | null, keys: string[]) => void): void;
  };
}

// Mock implementation for local development
class TelegramWebAppMock implements TelegramWebApp {
  // Generate mock init data
  public initData: string;
  public initDataUnsafe: TelegramWebApp['initDataUnsafe'];
  public version = '6.9';
  public platform = 'web';
  public colorScheme: 'light' | 'dark' = 'light';
  public themeParams = {
    bg_color: '#ffffff',
    text_color: '#000000',
    hint_color: '#999999',
    link_color: '#2481cc',
    button_color: '#2481cc',
    button_text_color: '#ffffff',
    secondary_bg_color: '#f1f1f1',
  };
  public isExpanded = true;
  public viewportHeight = 600;
  public viewportStableHeight = 600;
  public headerColor = '#2481cc';
  public backgroundColor = '#ffffff';
  public isClosingConfirmationEnabled = false;

  private eventHandlers: { [key: string]: Function[] } = {};
  private cloudStorage: { [key: string]: string } = {};

  constructor() {
    // Create mock user data
    const mockUser: TelegramUser = {
      id: Math.floor(Math.random() * 1000000) + 100000,
      first_name: 'Test',
      last_name: 'User',
      username: 'testuser',
      language_code: 'en',
      is_premium: false,
    };

    // Create mock init data
    const authDate = Math.floor(Date.now() / 1000);
    this.initDataUnsafe = {
      query_id: 'mock_query_id_' + Date.now(),
      user: mockUser,
      auth_date: authDate,
      hash: 'mock_hash_' + Math.random().toString(36).substr(2, 9),
      start_param: 'debug',
    };

    // Generate mock init data string
    this.initData = this.generateInitDataString();

    // Initialize buttons
    this.initializeMainButton();
    this.initializeBackButton();
    this.initializeSettingsButton();
    this.initializeHapticFeedback();
    this.initializeCloudStorage();

    console.log('[Telegram WebApp Mock] Initialized with user:', mockUser);
  }

  private generateInitDataString(): string {
    const params = new URLSearchParams();
    params.set('query_id', this.initDataUnsafe.query_id || '');
    params.set('user', JSON.stringify(this.initDataUnsafe.user));
    params.set('auth_date', this.initDataUnsafe.auth_date.toString());
    params.set('hash', this.initDataUnsafe.hash);
    if (this.initDataUnsafe.start_param) {
      params.set('start_param', this.initDataUnsafe.start_param);
    }
    return params.toString();
  }

  ready(): void {
    console.log('[Telegram WebApp Mock] Ready called');
    setTimeout(() => {
      this.triggerEvent('ready');
    }, 100);
  }

  close(): void {
    console.log('[Telegram WebApp Mock] Close called');
    this.triggerEvent('close');
  }

  expand(): void {
    console.log('[Telegram WebApp Mock] Expand called');
    this.isExpanded = true;
    this.viewportHeight = 800;
    this.triggerEvent('viewportChanged', { isStateStable: true });
  }

  enableClosingConfirmation(): void {
    this.isClosingConfirmationEnabled = true;
    console.log('[Telegram WebApp Mock] Closing confirmation enabled');
  }

  disableClosingConfirmation(): void {
    this.isClosingConfirmationEnabled = false;
    console.log('[Telegram WebApp Mock] Closing confirmation disabled');
  }

  onEvent(eventType: string, eventHandler: Function): void {
    if (!this.eventHandlers[eventType]) {
      this.eventHandlers[eventType] = [];
    }
    this.eventHandlers[eventType].push(eventHandler);
    console.log(`[Telegram WebApp Mock] Event listener added for: ${eventType}`);
  }

  offEvent(eventType: string, eventHandler: Function): void {
    if (this.eventHandlers[eventType]) {
      const index = this.eventHandlers[eventType].indexOf(eventHandler);
      if (index > -1) {
        this.eventHandlers[eventType].splice(index, 1);
        console.log(`[Telegram WebApp Mock] Event listener removed for: ${eventType}`);
      }
    }
  }

  private triggerEvent(eventType: string, data?: any): void {
    if (this.eventHandlers[eventType]) {
      this.eventHandlers[eventType].forEach(handler => {
        try {
          handler(data);
        } catch (error) {
          console.error(`[Telegram WebApp Mock] Error in event handler for ${eventType}:`, error);
        }
      });
    }
  }

  sendData(data: string): void {
    console.log('[Telegram WebApp Mock] Data sent:', data);
    this.triggerEvent('dataRequested', data);
  }

  showPopup(params: {
    title?: string;
    message: string;
    buttons?: Array<{
      id?: string;
      type?: 'default' | 'ok' | 'close' | 'cancel' | 'destructive';
      text: string;
    }>;
  }, callback?: (buttonId: string) => void): void {
    console.log('[Telegram WebApp Mock] Popup shown:', params);
    
    // Simulate user interaction
    setTimeout(() => {
      const buttons = params.buttons || [{ id: 'ok', text: 'OK', type: 'ok' }];
      const selectedButton = buttons[0];
      if (callback) {
        callback(selectedButton.id || selectedButton.type || 'ok');
      }
    }, 1000);
  }

  showAlert(message: string, callback?: () => void): void {
    console.log('[Telegram WebApp Mock] Alert shown:', message);
    setTimeout(() => {
      if (callback) callback();
    }, 1000);
  }

  showConfirm(message: string, callback?: (confirmed: boolean) => void): void {
    console.log('[Telegram WebApp Mock] Confirm shown:', message);
    setTimeout(() => {
      if (callback) callback(true); // Always confirm in mock
    }, 1000);
  }

  showScanQrPopup(params: { text?: string }, callback?: (data: string) => boolean): void {
    console.log('[Telegram WebApp Mock] QR scan popup shown:', params);
    setTimeout(() => {
      if (callback) {
        const mockQrData = 'mock_qr_data_' + Date.now();
        callback(mockQrData);
      }
    }, 2000);
  }

  closeScanQrPopup(): void {
    console.log('[Telegram WebApp Mock] QR scan popup closed');
  }

  readTextFromClipboard(callback?: (data: string) => void): void {
    console.log('[Telegram WebApp Mock] Reading from clipboard');
    setTimeout(() => {
      if (callback) {
        callback('mock clipboard content');
      }
    }, 500);
  }

  // Main Button implementation
  public MainButton = {
    text: '',
    color: '#2481cc',
    textColor: '#ffffff',
    isVisible: false,
    isActive: true,
    isProgressVisible: false,
    clickHandlers: [] as Function[],

    setText: (text: string) => {
      this.MainButton.text = text;
      console.log('[Telegram WebApp Mock] MainButton text set to:', text);
    },

    onClick: (callback: () => void) => {
      this.MainButton.clickHandlers.push(callback);
      console.log('[Telegram WebApp Mock] MainButton click handler added');
    },

    offClick: (callback: () => void) => {
      const index = this.MainButton.clickHandlers.indexOf(callback);
      if (index > -1) {
        this.MainButton.clickHandlers.splice(index, 1);
        console.log('[Telegram WebApp Mock] MainButton click handler removed');
      }
    },

    show: () => {
      this.MainButton.isVisible = true;
      console.log('[Telegram WebApp Mock] MainButton shown');
    },

    hide: () => {
      this.MainButton.isVisible = false;
      console.log('[Telegram WebApp Mock] MainButton hidden');
    },

    enable: () => {
      this.MainButton.isActive = true;
      console.log('[Telegram WebApp Mock] MainButton enabled');
    },

    disable: () => {
      this.MainButton.isActive = false;
      console.log('[Telegram WebApp Mock] MainButton disabled');
    },

    showProgress: (leaveActive?: boolean) => {
      this.MainButton.isProgressVisible = true;
      if (!leaveActive) {
        this.MainButton.isActive = false;
      }
      console.log('[Telegram WebApp Mock] MainButton progress shown');
    },

    hideProgress: () => {
      this.MainButton.isProgressVisible = false;
      this.MainButton.isActive = true;
      console.log('[Telegram WebApp Mock] MainButton progress hidden');
    },

    setParams: (params: {
      text?: string;
      color?: string;
      text_color?: string;
      is_active?: boolean;
      is_visible?: boolean;
    }) => {
      if (params.text !== undefined) this.MainButton.text = params.text;
      if (params.color !== undefined) this.MainButton.color = params.color;
      if (params.text_color !== undefined) this.MainButton.textColor = params.text_color;
      if (params.is_active !== undefined) this.MainButton.isActive = params.is_active;
      if (params.is_visible !== undefined) this.MainButton.isVisible = params.is_visible;
      console.log('[Telegram WebApp Mock] MainButton params set:', params);
    },

    triggerClick: () => {
      if (this.MainButton.isVisible && this.MainButton.isActive && !this.MainButton.isProgressVisible) {
        this.MainButton.clickHandlers.forEach(handler => {
          try {
            handler();
          } catch (error) {
            console.error('[Telegram WebApp Mock] Error in MainButton click handler:', error);
          }
        });
      }
    }
  };

  // Back Button implementation
  public BackButton = {
    isVisible: false,
    clickHandlers: [] as Function[],

    onClick: (callback: () => void) => {
      this.BackButton.clickHandlers.push(callback);
      console.log('[Telegram WebApp Mock] BackButton click handler added');
    },

    offClick: (callback: () => void) => {
      const index = this.BackButton.clickHandlers.indexOf(callback);
      if (index > -1) {
        this.BackButton.clickHandlers.splice(index, 1);
        console.log('[Telegram WebApp Mock] BackButton click handler removed');
      }
    },

    show: () => {
      this.BackButton.isVisible = true;
      console.log('[Telegram WebApp Mock] BackButton shown');
    },

    hide: () => {
      this.BackButton.isVisible = false;
      console.log('[Telegram WebApp Mock] BackButton hidden');
    }
  };

  // Settings Button implementation
  public SettingsButton = {
    isVisible: false,
    clickHandlers: [] as Function[],

    onClick: (callback: () => void) => {
      this.SettingsButton.clickHandlers.push(callback);
      console.log('[Telegram WebApp Mock] SettingsButton click handler added');
    },

    offClick: (callback: () => void) => {
      const index = this.SettingsButton.clickHandlers.indexOf(callback);
      if (index > -1) {
        this.SettingsButton.clickHandlers.splice(index, 1);
        console.log('[Telegram WebApp Mock] SettingsButton click handler removed');
      }
    },

    show: () => {
      this.SettingsButton.isVisible = true;
      console.log('[Telegram WebApp Mock] SettingsButton shown');
    },

    hide: () => {
      this.SettingsButton.isVisible = false;
      console.log('[Telegram WebApp Mock] SettingsButton hidden');
    }
  };

  // Haptic Feedback implementation
  public HapticFeedback = {
    impactOccurred: (style: 'light' | 'medium' | 'heavy' | 'rigid' | 'soft') => {
      console.log(`[Telegram WebApp Mock] Haptic impact: ${style}`);
    },

    notificationOccurred: (type: 'error' | 'success' | 'warning') => {
      console.log(`[Telegram WebApp Mock] Haptic notification: ${type}`);
    },

    selectionChanged: () => {
      console.log('[Telegram WebApp Mock] Haptic selection changed');
    }
  };

  // Cloud Storage implementation
  public CloudStorage = {
    setItem: (key: string, value: string, callback?: (error: string | null, success: boolean) => void) => {
      this.cloudStorage[key] = value;
      console.log(`[Telegram WebApp Mock] CloudStorage set: ${key} = ${value}`);
      if (callback) {
        setTimeout(() => callback(null, true), 100);
      }
    },

    getItem: (key: string, callback: (error: string | null, value: string) => void) => {
      const value = this.cloudStorage[key] || '';
      console.log(`[Telegram WebApp Mock] CloudStorage get: ${key} = ${value}`);
      setTimeout(() => callback(null, value), 100);
    },

    getItems: (keys: string[], callback: (error: string | null, values: {[key: string]: string}) => void) => {
      const values: {[key: string]: string} = {};
      keys.forEach(key => {
        values[key] = this.cloudStorage[key] || '';
      });
      console.log('[Telegram WebApp Mock] CloudStorage getItems:', values);
      setTimeout(() => callback(null, values), 100);
    },

    removeItem: (key: string, callback?: (error: string | null, success: boolean) => void) => {
      delete this.cloudStorage[key];
      console.log(`[Telegram WebApp Mock] CloudStorage removed: ${key}`);
      if (callback) {
        setTimeout(() => callback(null, true), 100);
      }
    },

    removeItems: (keys: string[], callback?: (error: string | null, success: boolean) => void) => {
      keys.forEach(key => {
        delete this.cloudStorage[key];
      });
      console.log('[Telegram WebApp Mock] CloudStorage removed keys:', keys);
      if (callback) {
        setTimeout(() => callback(null, true), 100);
      }
    },

    getKeys: (callback: (error: string | null, keys: string[]) => void) => {
      const keys = Object.keys(this.cloudStorage);
      console.log('[Telegram WebApp Mock] CloudStorage keys:', keys);
      setTimeout(() => callback(null, keys), 100);
    }
  };

  private initializeMainButton(): void {
    // Add click simulation for development
    if (typeof window !== 'undefined') {
      const simulateMainButtonClick = () => {
        (this.MainButton as any).triggerClick();
      };
      
      // Add to window for manual testing
      (window as any).__telegramMainButtonClick = simulateMainButtonClick;
    }
  }

  private initializeBackButton(): void {
    // Implementation for back button initialization
  }

  private initializeSettingsButton(): void {
    // Implementation for settings button initialization
  }

  private initializeHapticFeedback(): void {
    // Implementation for haptic feedback initialization
  }

  private initializeCloudStorage(): void {
    // Load from localStorage if available
    if (typeof window !== 'undefined' && window.localStorage) {
      try {
        const stored = localStorage.getItem('telegram_cloud_storage_mock');
        if (stored) {
          this.cloudStorage = JSON.parse(stored);
        }
      } catch (error) {
        console.warn('[Telegram WebApp Mock] Failed to load cloud storage from localStorage:', error);
      }

      // Save to localStorage on changes
      const originalSetItem = this.CloudStorage.setItem;
      this.CloudStorage.setItem = (key: string, value: string, callback?: (error: string | null, success: boolean) => void) => {
        originalSetItem(key, value, callback);
        try {
          localStorage.setItem('telegram_cloud_storage_mock', JSON.stringify(this.cloudStorage));
        } catch (error) {
          console.warn('[Telegram WebApp Mock] Failed to save cloud storage to localStorage:', error);
        }
      };
    }
  }
}

// Initialize mock when not in Telegram environment
export function initializeTelegramMock(): TelegramWebApp {
  const isInTelegram = typeof window !== 'undefined' && 
                      window.Telegram && 
                      window.Telegram.WebApp;

  if (isInTelegram) {
    console.log('[Telegram WebApp] Using real Telegram WebApp API');
    return window.Telegram.WebApp;
  } else {
    console.log('[Telegram WebApp Mock] Initializing mock for local development');
    const mock = new TelegramWebAppMock();
    
    // Add to window for debugging
    if (typeof window !== 'undefined') {
      (window as any).Telegram = {
        WebApp: mock
      };
    }
    
    return mock;
  }
}

// Export types for TypeScript support
export type { TelegramWebApp };

// Global window extension
declare global {
  interface Window {
    Telegram?: {
      WebApp: TelegramWebApp;
    };
    __telegramMainButtonClick?: () => void;
  }
}