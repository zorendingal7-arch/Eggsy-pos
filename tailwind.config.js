module.exports = {
  darkMode: "class",
  content: [
    "./**/*.php",
    "./**/*.html",
  ],
  theme: {
    extend: {
      colors: {
        "primary": "#f2c40d",
        "background-light": "#f8f8f5",
        "background-dark": "#221e10",
      },
      fontFamily: { "display": ["Plus Jakarta Sans"] },
      borderRadius: {
        "DEFAULT": "0.25rem",
        "lg": "0.5rem",
        "xl": "0.75rem",
        "full": "9999px"
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}