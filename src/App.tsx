import { Routes, Route } from 'react-router'
import Home from './pages/Home'
import Blog from './pages/Blog'
import BlogPost from './pages/BlogPost'

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Home />} />
      <Route path="/blog" element={<Blog />} />
      <Route path="/blog/:slug" element={<BlogPost />} />
    </Routes>
  )
}
