<script setup lang="ts">
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { IconHome, IconTarget, IconTrophy, IconUser } from '@tabler/icons-vue'
import { cn } from '@/lib/utils'

const page = usePage()

const navigationItems = [
  {
    name: 'Home',
    href: '/',
    icon: IconHome,
    activeRoutes: ['dashboard', 'home']
  },
  {
    name: 'Questions',
    href: '/questions/daily',
    icon: IconTarget,
    activeRoutes: ['questions.daily']
  },
  {
    name: 'Leaderboard', 
    href: '/leaderboard',
    icon: IconTrophy,
    activeRoutes: ['leaderboard.index', 'leaderboard.daily']
  },
  {
    name: 'Profile',
    href: '/profile',
    icon: IconUser,
    activeRoutes: ['profile.show']
  }
]

const isActive = (routes: string[]) => {
  const currentComponent = page.component as string
  const currentUrl = page.url
  
  return routes.some(route => {
    // Check component names (e.g., 'Dashboard', 'Questions/Daily')
    if (currentComponent && currentComponent.includes(route)) {
      return true
    }
    // Check URLs
    if (route === 'dashboard' || route === 'home') {
      return currentUrl === '/' || currentComponent === 'Dashboard'
    }
    if (route === 'questions.daily') {
      return currentUrl === '/questions/daily' || currentComponent === 'Questions/Daily'
    }
    if (route.startsWith('leaderboard')) {
      return currentUrl.includes('/leaderboard') || currentComponent?.includes('Leaderboard')
    }
    if (route === 'profile.show') {
      return currentUrl.includes('/profile') || currentComponent?.includes('Profile')
    }
    return false
  })
}
</script>

<template>
  <nav class="fixed bottom-0 left-0 right-0 bg-background border-t border-border z-50">
    <div class="flex items-center justify-around h-16 px-2">
      <Link 
        v-for="item in navigationItems" 
        :key="item.name"
        :href="item.href"
        class="flex flex-col items-center justify-center flex-1 py-2 px-1 text-center transition-colors duration-200"
        :class="cn(
          'text-muted-foreground hover:text-foreground',
          isActive(item.activeRoutes) && 'text-primary font-medium'
        )"
      >
        <component 
          :is="item.icon" 
          class="h-5 w-5 mb-1"
          :class="cn(
            isActive(item.activeRoutes) && 'text-primary'
          )"
        />
        <span class="text-xs">{{ item.name }}</span>
      </Link>
    </div>
  </nav>
</template>

<style scoped>
/* Add bottom padding to body/main content to prevent overlap */
</style>