/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./index.php",
    "./includes/**/*.php",
    "./pages/**/*.php",
    "./api/**/*.php",
    "./*.md",
    "./*.html"
  ],
  theme: {
    extend: {
      colors: {
        primary: "#0f172a",
        secondary: "#2563eb",
        accent: {
          red: "#ef4444",
          gray: "#1e293b",
          light: "#f1f5f9"
        },
        bg: "#f8fafc",
        success: "#10b981"
      },
      fontFamily: {
        sans: ["Inter", "sans-serif"],
        heading: ["Montserrat", "sans-serif"]
      }
    }
  },
  plugins: []
};
