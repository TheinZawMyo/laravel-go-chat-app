<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="h-dvh w-full overflow-hidden bg-chat-canvas text-foreground">
        <slot />
        <Toaster />
    </div>
</template>
