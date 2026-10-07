import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Drupal always serves main.js with a cache-busting query string
// (JsCollectionRenderer), but the relative imports Rollup writes into the
// lazily loaded chunks have no query string. So a chunk importing back from
// "./main.js" resolves to a different URL than the one the browser already
// loaded, and the entry gets evaluated a second time. CodeMirror then holds
// two copies of @codemirror/state, and the `instanceof` checks it relies on
// fail — which is why code blocks in any lazily loaded language (YAML,
// Python, ...) mounted but silently swallowed every keystroke.
//
// Moving the entry module into its own chunk leaves main.js as a tiny facade
// that nothing imports from, so shared code is always reached through a
// stable, query-string-free chunk URL. This holds for any language or plugin
// added later — there is no list to keep up to date.
const ENTRY = './src/main.jsx'

/**
 * Fails the build if any chunk imports the entry, in case the above regresses.
 */
function assertNothingImportsEntry() {
  return {
    name: 'assert-nothing-imports-entry',
    generateBundle(options, bundle) {
      const chunks = Object.values(bundle).filter((file) => file.type === 'chunk')
      const entry = chunks.find((chunk) => chunk.isEntry)
      const offenders = chunks.filter(
        (chunk) => !chunk.isEntry && chunk.imports.includes(entry.fileName),
      )

      if (offenders.length) {
        this.error(
          `${offenders.length} chunk(s) import the entry "${entry.fileName}": ` +
            `${offenders.map((chunk) => chunk.fileName).join(', ')}. ` +
            'Drupal serves the entry with a cache-busting query string, so ' +
            'these would load a duplicate copy of it at runtime. Keep the ' +
            `entry module (${ENTRY}) assigned to its own manual chunk.`,
        )
      }
    },
  }
}

export default defineConfig({
  plugins: [react(), assertNothingImportsEntry()],
  build: {
    rollupOptions: {
      output: {
        entryFileNames: 'assets/main.js',
        chunkFileNames: 'assets/chunk-[name].js',
        assetFileNames: 'assets/[name][extname]',
        // Named "index" so the entry's stylesheet keeps its index.css
        // filename, which ai.libraries.yml references.
        manualChunks: { index: [ENTRY] },
      },
    },
  },
})
