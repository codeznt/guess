<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Button } from '@/components/ui/button';
import { telegram } from '@/routes/auth';
import { dashboard } from '@/routes';
import { LoaderCircle } from 'lucide-vue-next';

const processing = ref(false);
const isLocalDev = ref(false);

// Check if we're in a local development environment
onMounted(() => {
  const hostname = window.location.hostname;
  isLocalDev.value = hostname === 'localhost' || hostname === '127.0.0.1';
});

// Mock Telegram data for local development
const mockTelegramData = {
  telegram_user: {
    id: 12345678,
    first_name: 'Test',
    last_name: 'User',
    username: 'testuser',
    language_code: 'en',
    is_premium: false
  },
  hash: 'mock_hash_validation'
};

const form = useForm({
  telegram_user: {},
  hash: ''
});

function handleTelegramLogin() {
  processing.value = true;
  
  if (isLocalDev.value) {
    // Use mock data for local development
    form.telegram_user = mockTelegramData.telegram_user;
    form.hash = mockTelegramData.hash;
    
    // Submit the form with mock data
    form.post(telegram.url(), {
      preserveScroll: true,
      onSuccess: () => {
        window.location.href = dashboard.url();
      },
      onError: (errors) => {
        console.error('Login error:', errors);
        processing.value = false;
      },
      onFinish: () => {
        if (processing.value) processing.value = false;
      }
    });
  } else {
    // In production, this would be handled by the Telegram WebApp
    // The Telegram WebApp will provide the user data and hash
    if (window.Telegram && window.Telegram.WebApp) {
      const telegramWebApp = window.Telegram.WebApp;
      
      try {
        // Get user data from Telegram WebApp
        const userData = telegramWebApp.initDataUnsafe.user;
        const hash = telegramWebApp.initData.split('&').find(param => param.startsWith('hash='))?.split('=')[1];
        
        if (userData && hash) {
          form.telegram_user = userData;
          form.hash = hash;
          
          // Submit the form with Telegram data
          form.post(telegram.url(), {
            preserveScroll: true,
            onSuccess: () => {
              window.location.href = dashboard.url();
            },
            onError: (errors) => {
              console.error('Login error:', errors);
              processing.value = false;
            },
            onFinish: () => {
              if (processing.value) processing.value = false;
            }
          });
        } else {
          throw new Error('Missing user data or hash');
        }
      } catch (error) {
        console.error('Telegram WebApp error:', error);
        processing.value = false;
        alert('Failed to get Telegram user data. Please try again.');
      }
    } else {
      processing.value = false;
      alert('This app should be opened from Telegram.');
    }
  }
}
</script>

<template>
  <AuthBase title="Log in with Telegram" description="Click the button below to log in with your Telegram account">
    <Head title="Telegram Login" />

    <div class="flex flex-col gap-6">
      <div class="grid gap-6">
        <Button 
          @click="handleTelegramLogin" 
          class="w-full flex items-center justify-center gap-2" 
          :disabled="processing"
        >
          <LoaderCircle v-if="processing" class="h-4 w-4 animate-spin" />
          <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 0C5.376 0 0 5.376 0 12C0 18.624 5.376 24 12 24C18.624 24 24 18.624 24 12C24 5.376 18.624 0 12 0ZM17.568 8.16C17.388 10.056 16.608 14.664 16.212 16.788C16.044 17.688 15.708 18 15.396 18.036C14.7 18.108 14.172 17.592 13.5 17.148C12.444 16.464 11.844 16.044 10.824 15.384C9.636 14.628 10.404 14.208 11.088 13.5C11.268 13.32 14.34 10.536 14.4 10.284C14.412 10.248 14.412 10.104 14.328 10.032C14.244 9.96 14.124 9.984 14.04 10.008C13.92 10.044 12.24 11.184 8.988 13.428C8.508 13.764 8.076 13.92 7.692 13.908C7.272 13.896 6.468 13.668 5.868 13.476C5.112 13.236 4.524 13.104 4.572 12.684C4.596 12.468 4.896 12.24 5.46 12.012C8.964 10.5 11.292 9.492 12.444 8.988C15.864 7.524 16.644 7.236 17.16 7.236C17.268 7.236 17.532 7.272 17.7 7.404C17.832 7.512 17.856 7.668 17.868 7.776C17.856 7.86 17.88 8.052 17.568 8.16Z" fill="currentColor"/>
          </svg>
          {{ isLocalDev ? 'Login with Mock Telegram Data' : 'Login with Telegram' }}
        </Button>
        
        <div v-if="isLocalDev" class="text-center text-sm text-muted-foreground">
          <p>Development mode: Using mock Telegram data</p>
        </div>
      </div>
    </div>
  </AuthBase>
</template>
