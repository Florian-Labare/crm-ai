/** @type {import('tailwindcss').Config} */
export default {
    content: [
      "./index.html",          // ton point d’entrée HTML
      "./src/**/*.{js,ts,jsx,tsx}", // tous tes fichiers React / TypeScript dans src/
    ],
    theme: {
      extend: {
        colors: {
          primary: "#2563eb",  // bleu principal
          secondary: "#1e293b", // gris foncé
        },
      },
    },
    plugins: [],
  }
  