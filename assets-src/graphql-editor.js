// advanced-graphql-editor.js
import { basicSetup } from 'codemirror'
import { EditorView, keymap } from '@codemirror/view'
import { defaultKeymap, indentWithTab } from '@codemirror/commands'
import { EditorState } from '@codemirror/state'
import {
  autocompletion,
  completionKeymap,
  closeBrackets,
  closeBracketsKeymap
} from '@codemirror/autocomplete'
import {
  indentOnInput,
  bracketMatching,
  foldGutter,
  foldKeymap
} from '@codemirror/language'
import { lintKeymap } from '@codemirror/lint'
import { oneDark } from '@codemirror/theme-one-dark'
import { graphql } from 'cm6-graphql'
import { buildSchema } from 'graphql'

export class GraphQLEditor {
  constructor(container, options = {}) {
    const { theme, schema, initialValue, height, renderSchema, ...rest } =
      options
    this.schema = null
    this.container = container
    this.options = {
      theme: theme || this.detectColorScheme(),
      schema: schema || null,
      initialValue: initialValue || '',
      height: height || '300px',
      renderSchema: renderSchema || false,
      ...rest
    }

    this.view = null
    this.init()
  }

  init() {
    this.schema = buildSchema(this.options.schema)

    const extensions = [
      basicSetup,
      EditorView.theme({
        '&': {
          height: this.options.height,
          fontSize: '12px'
        }
        // '.cm-scroller': {
        //   fontFamily:
        //     'Monaco, Consolas, "Liberation Mono", "Courier New", monospace'
        // },
        // '.cm-focused': {
        //   outline: '2px solid #e91e63'
        // }
      })
    ]

    if (!this.options.renderSchema) {
      const graphqlKeymap = keymap.of([
        ...defaultKeymap,
        ...completionKeymap,
        ...foldKeymap,
        ...closeBracketsKeymap,
        ...lintKeymap,
        indentWithTab
      ])

      extensions.push(
        foldGutter(),
        indentOnInput(),
        closeBrackets(),
        autocompletion(),
        bracketMatching(),
        graphqlKeymap
      )
    } else {
      extensions.push(EditorState.readOnly.of(true))
      extensions.push(EditorView.editable.of(false))
    }

    if (this.options.theme) {
      extensions.push(this.options.theme)
    }

    // Add GraphQL language support
    if (this.schema) {
      extensions.push(graphql(this.schema))
      // extensions.push(this.createGraphQLCompletion(this.schema))
    } else {
      extensions.push(graphql())
    }

    if (!this.options.renderSchema && this.options.onChange) {
      extensions.push(
        EditorView.updateListener.of((update) => {
          if (update.docChanged) {
            this.options.onChange(update.state.doc.toString())
          }
        })
      )
    }

    const startState = EditorState.create({
      doc: this.options.initialValue,
      extensions
    })

    this.view = new EditorView({
      state: startState,
      parent: this.container
    })
    console.log(
      'GraphQLEditor initialized with view:',
      this.view,
      this.container
    )

    window.formatQuery = function () {
      // Simple GraphQL formatting
      const doc = editor.state.doc
      const formatted = doc
        .toString()
        .replace(/\s*{\s*/g, ' {\n  ')
        .replace(/\s*}\s*/g, '\n}\n')
        .replace(/,\s*/g, ',\n  ')
        .trim()

      editor.dispatch({
        changes: { from: 0, to: doc.length, insert: formatted }
      })
    }

    if (this.schema && this.options.renderSchema)
      this.renderSchema(this.options.schema)
  }

  getValue() {
    return this.view.state.doc.toString()
  }

  setValue(content) {
    this.view.dispatch({
      changes: {
        from: 0,
        to: this.view.state.doc.length,
        insert: content
      }
    })
  }

  destroy() {
    if (this.view) {
      this.view.destroy()
      this.view = null
    }
  }

  renderSchema(schema) {
    this.view.dispatch({
      changes: {
        from: 0,
        to: this.view.state.doc.length,
        insert: schema.toString()
      }
    })
    // const sdlDiv = document.createElement('div')
    // sdlDiv.className = 'schema-sdl'
    // sdlDiv.innerHTML = this.schemaToSDL(schema)
    // schemaContent.appendChild(sdlDiv)
  }
  schemaToSDL(schema) {
    if (!schema.getTypeMap) {
      return '# Schema format not supported for SDL display'
    }

    const typeMap = schema.getTypeMap()
    let sdl = ''

    // Add Query, Mutation, Subscription types first
    const rootTypes = ['Query', 'Mutation', 'Subscription']
    rootTypes.forEach((rootType) => {
      if (typeMap[rootType] && typeMap[rootType].getFields) {
        sdl += this.formatTypeToSDL(typeMap[rootType], rootType) + '\n\n'
      }
    })

    // Add other types
    Object.keys(typeMap).forEach((typeName) => {
      if (typeName.startsWith('__')) return // Skip introspection types
      if (rootTypes.includes(typeName)) return // Already added

      const type = typeMap[typeName]
      if (type.getFields) {
        sdl += this.formatTypeToSDL(type, typeName) + '\n\n'
      }
    })

    return sdl.trim()
  }
  formatTypeToSDL(type, typeName) {
    let sdl = `<span class="keyword">type</span> <span class="type-name">${typeName}</span> {\n`

    try {
      const fields = type.getFields()
      Object.keys(fields).forEach((fieldName) => {
        const field = fields[fieldName]
        sdl += `  <span class="field-name">${fieldName}</span>`

        // Add arguments
        if (field.args && field.args.length > 0) {
          const argStrings = field.args.map(
            (arg) =>
              `${arg.name}: <span class="field-type">${arg.type.toString()}</span>`
          )
          sdl += `(${argStrings.join(', ')})`
        }

        sdl += `: <span class="field-type">${field.type.toString()}</span>\n`
      })
    } catch (e) {
      sdl += `  # Error reading fields\n`
    }

    sdl += `}`
    return sdl
  }

  detectColorScheme() {
    // Detect user's color scheme preference
    const darkMode = !document.body.classList.contains('rex-theme-light')
    return darkMode ? oneDark : null
  }
}
