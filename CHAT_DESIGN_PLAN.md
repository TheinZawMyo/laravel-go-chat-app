# Chat UI Design Plan

## Goal

Refresh the current chat screen so it feels closer to Telegram Web: compact, calm, easy to scan, and centered on the conversation. Keep the implementation straightforward and preserve the existing chat behavior, API, and data model.

## Current UI

The chat page already has the core pieces: a conversation list with search, a message header, a virtualized message timeline, and a composer. The visual treatment is currently minimal and mostly uses the shared neutral theme. The main files are:

- `apps/web/resources/js/pages/Chat.vue` — two-pane page layout and active conversation header.
- `apps/web/resources/js/components/chat/ConversationList.vue` — search and conversation rows.
- `apps/web/resources/js/components/chat/MessageTimeline.vue` — message scrolling, loading, and empty state.
- `apps/web/resources/js/components/chat/MessageBubble.vue` — individual message presentation.
- `apps/web/resources/js/components/chat/MessageComposer.vue` — reply preview and message input.
- `apps/web/resources/css/app.css` — shared light and dark theme tokens.

## Design Direction

- Use a familiar two-column desktop layout: a fixed-width chat rail and a flexible conversation pane.
- Give the chat rail a slightly distinct surface, with a clear search field and denser conversation rows.
- Use one restrained Telegram-like blue as the accent for selection, links, and sent messages; retain readable light and dark themes.
- Make received and sent messages easy to distinguish with soft, rounded bubbles, modest spacing, and clear timestamps.
- Keep headers and the composer visually quiet so message history remains the focus.
- Use existing avatars, unread counts, message previews, and participant names. Do not imply online status or other information the app does not currently provide.

## Implementation Steps

### 1. Establish the chat canvas — completed

- Added `ChatLayout.vue` and assigned it to the Chat page so messaging uses the full viewport without the generic dashboard sidebar and breadcrumb header.
- Built a dedicated two-pane canvas in `Chat.vue` with a 400px desktop chat rail and a flexible conversation area.
- Preserved the mobile list-to-conversation flow and back action.
- Replaced the plain no-selection state with a centered welcome panel.

### 2. Improve the conversation rail — completed

- Refined `ConversationList.vue` spacing, search field treatment, avatar sizing, and selected-row styling.
- Added working All chats and Unread filters and made unread names, previews, timestamps, and counts easier to scan.
- Added an account menu that retains access to profile settings and sign out.
- Added clear hover, keyboard focus, and selected states that work in light and dark mode.
- Kept current search and new-conversation interactions unchanged.

### 3. Style the message history — completed

- Restyled `MessageBubble.vue` with compact incoming and outgoing surfaces, clearer spacing, and readable metadata.
- Added a subtle chat canvas and refined the spacing and history controls in `MessageTimeline.vue`.
- Preserved virtualization, scroll anchoring, loading, and empty-history behavior.

### 4. Polish the header and composer — completed

- Improved the active conversation header hierarchy and refresh action appearance in `Chat.vue`.
- Restyled the reply preview and message entry surface in `MessageComposer.vue`, with a prominent send action and unobtrusive keyboard guidance.
- Retained visible focus treatment and touch-sized controls.

### 5. Apply shared color tokens — completed

- Added chat-specific accent, canvas, and outgoing-bubble tokens to `app.css` for light and dark themes.
- Applied the tokens to the chat rail, timeline, bubbles, and composer without changing global primary colors.
- Used the existing Tailwind and UI component patterns; no styling dependency was added.

## Acceptance Criteria

- The desktop chat view has a clear, Telegram Web–inspired two-pane composition and recognizable chat-row and message-bubble styling.
- The chat workspace fills the viewport without the generic dashboard shell.
- Mobile still supports the list-to-conversation flow and its back button.
- Search, conversation selection, unread counts, message loading, reply, send, and scroll behavior remain intact.
- Empty, loading, selected, hover, focus, and error states receive consistent styling.
- Light and dark themes remain legible and visually coherent.
- No backend, API, or database changes are needed for this visual refresh.

## Suggested Review

Review the chat screen at desktop and narrow mobile widths, in both light and dark themes. Verify the visual states above while exercising the existing interactions. Keep the first pass focused on layout, spacing, color, and typography; defer new chat capabilities to separate work.
