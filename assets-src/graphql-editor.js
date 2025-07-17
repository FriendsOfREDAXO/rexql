// advanced-graphql-editor.js
import { EditorView, basicSetup } from 'codemirror'
import { EditorState } from '@codemirror/state'
import { oneDark } from '@codemirror/theme-one-dark'
import { graphql } from 'cm6-graphql'
import { buildSchema } from 'graphql'

export class GraphQLEditor {
  constructor(container, options = {}) {
    this.container = container
    this.options = {
      theme: options.theme || oneDark,
      schema: options.schema || null,
      initialValue: options.initialValue || '',
      height: options.height || '400px',
      ...options
    }

    this.view = null
    this.init()
  }

  init() {
    const extensions = [
      basicSetup,
      this.options.theme,
      EditorView.theme({
        '&': {
          height: this.options.height,
          fontSize: '14px'
        },
        '.cm-scroller': {
          fontFamily:
            'Monaco, Consolas, "Liberation Mono", "Courier New", monospace'
        },
        '.cm-focused': {
          outline: '2px solid #e91e63'
        }
      })
    ]

    // Add GraphQL language support
    if (this.options.schema) {
      extensions.push(graphql(buildSchema(this.options.schema)))
    } else {
      extensions.push(graphql())
    }

    if (this.options.onChange) {
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
  }

  // Get current content
  getValue() {
    return this.view.state.doc.toString()
  }

  // Set content
  setValue(content) {
    this.view.dispatch({
      changes: {
        from: 0,
        to: this.view.state.doc.length,
        insert: content
      }
    })
  }

  // Update schema
  updateSchema(schemaString) {
    try {
      const schema = buildSchema(schemaString)
      // You would need to recreate the editor with new schema
      // This is a simplified version
      this.options.schema = schemaString
      this.destroy()
      this.init()
    } catch (error) {
      console.error('Invalid schema:', error)
    }
  }

  // Destroy editor
  destroy() {
    if (this.view) {
      this.view.destroy()
      this.view = null
    }
  }
}

// Usage example
const sampleSchema = `
  type Query {
    user(id: ID!): User
    users: [User]
    post(id: ID!): Post
    posts: [Post]
  }

  type Mutation {
    createUser(input: CreateUserInput!): User
    updateUser(id: ID!, input: UpdateUserInput!): User
    deleteUser(id: ID!): Boolean
    createPost(input: CreatePostInput!): Post
    updatePost(id: ID!, input: UpdatePostInput!): Post
    deletePost(id: ID!): Boolean
  }

  type User {
    id: ID!
    name: String!
    email: String!
    posts: [Post]
    createdAt: String!
    updatedAt: String!
  }

  type Post {
    id: ID!
    title: String!
    content: String!
    author: User!
    published: Boolean!
    createdAt: String!
    updatedAt: String!
  }

  input CreateUserInput {
    name: String!
    email: String!
  }

  input UpdateUserInput {
    name: String
    email: String
  }

  input CreatePostInput {
    title: String!
    content: String!
    authorId: ID!
  }

  input UpdatePostInput {
    title: String
    content: String
    published: Boolean
  }
`

const initialQuery = `query GetUserWithPosts($userId: ID!) {
  user(id: $userId) {
    id
    name
    email
    posts {
      id
      title
      content
      published
      createdAt
    }
  }
}

query GetAllPosts {
  posts {
    id
    title
    content
    published
    author {
      id
      name
      email
    }
  }
}

mutation CreateNewUser($input: CreateUserInput!) {
  createUser(input: $input) {
    id
    name
    email
    createdAt
  }
}

mutation CreateNewPost($input: CreatePostInput!) {
  createPost(input: $input) {
    id
    title
    content
    author {
      name
    }
    published
    createdAt
  }
}`

// // Initialize the editor

// // Add change listener
// graphqlEditor.onChange((content) => {
//   console.log('Content changed:', content)
// })

// Export for global use
// window.graphqlEditor = editor

// Add some utility functions to window for testing
// export const editorUtils = {
//   getValue: () => editor.getValue(),
//   setValue: (content) => editor.setValue(content),
//   updateSchema: (schema) => editor.updateSchema(schema)
// }
