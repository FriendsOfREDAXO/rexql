module.exports = {
  plugins: {
    autoprefixer: {
      overrideBrowserslist: [
        '> 1%',
        'last 2 versions',
        'Firefox ESR',
        'not dead',
        'not ie 11'
      ]
    }
  }
}
