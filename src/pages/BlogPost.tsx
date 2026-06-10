import { useParams, Link } from 'react-router'
import { trpc } from '@/providers/trpc'
import Navigation from '@/components/Navigation'
import FooterSection from '@/sections/FooterSection'

export default function BlogPost() {
  const { slug } = useParams<{ slug: string }>()
  const { data: post, isLoading } = trpc.blog.getBySlug.useQuery(
    { slug: slug || '' },
    { enabled: !!slug }
  )

  if (isLoading) {
    return (
      <div className="relative" style={{ backgroundColor: '#141414', minHeight: '100vh' }}>
        <Navigation />
        <div className="text-center py-32">
          <p className="text-secondary-light">Загрузка статьи...</p>
        </div>
      </div>
    )
  }

  if (!post) {
    return (
      <div className="relative" style={{ backgroundColor: '#141414', minHeight: '100vh' }}>
        <Navigation />
        <div className="text-center py-32">
          <h1 className="text-white text-2xl mb-4">Статья не найдена</h1>
          <Link
            to="/blog"
            className="font-mono-accent text-[#2C2CFF] border-b border-[#2C2CFF] pb-1"
          >
            ВЕРНУТЬСЯ В БЛОГ
          </Link>
        </div>
      </div>
    )
  }

  return (
    <div className="relative">
      <Navigation />

      {/* Article Hero */}
      <section
        className="relative w-full"
        style={{ backgroundColor: '#141414', paddingTop: '140px', paddingBottom: '60px' }}
      >
        <div className="grid-container">
          <div className="col-span-8 col-start-3">
            <Link
              to="/blog"
              className="font-mono-accent text-secondary-light hover:text-white transition-colors mb-6 inline-block"
              style={{ fontSize: '11px' }}
            >
              ← НАЗАД В БЛОГ
            </Link>
            <h1
              className="text-white font-medium"
              style={{
                fontSize: 'clamp(28px, 4vw, 48px)',
                lineHeight: '1.1',
                letterSpacing: '-0.03em',
              }}
            >
              {post.title}
            </h1>
            <p className="text-secondary-dark mt-4" style={{ fontSize: '14px' }}>
              {post.createdAt
                ? new Date(post.createdAt).toLocaleDateString('ru-RU', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                  })
                : ''}
            </p>
          </div>
        </div>
      </section>

      {/* Article Content */}
      <section className="section-light" style={{ paddingTop: '60px' }}>
        <div className="grid-container">
          <div className="col-span-8 col-start-3">
            {post.coverImage && (
              <div className="mb-10 overflow-hidden">
                {post.coverImage.endsWith('.mp4') ? (
                  <video autoPlay loop muted playsInline className="w-full">
                    <source src={post.coverImage} type="video/mp4" />
                  </video>
                ) : (
                  <img
                    src={post.coverImage}
                    alt={post.title}
                    className="w-full h-auto"
                  />
                )}
              </div>
            )}

            {/* Article Body */}
            <article
              className="blog-article"
              dangerouslySetInnerHTML={{ __html: post.content }}
            />

            {/* Tags / Keywords */}
            {post.keywords && (
              <div className="mt-12 pt-8" style={{ borderTop: '1px solid #EAEAEA' }}>
                <div className="flex flex-wrap gap-2">
                  {post.keywords.split(',').map((kw) => (
                    <span
                      key={kw}
                      className="font-mono-accent px-3 py-1"
                      style={{
                        fontSize: '10px',
                        border: '1px solid #EAEAEA',
                        color: '#666666',
                      }}
                    >
                      {kw.trim().toUpperCase()}
                    </span>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      </section>

      {/* CTA Banner */}
      <section
        style={{ backgroundColor: '#141414', padding: '80px 0', textAlign: 'center' }}
      >
        <div className="grid-container">
          <div className="col-span-12">
            <h2
              className="text-white font-medium mb-4"
              style={{ fontSize: '28px', lineHeight: '1.2' }}
            >
              Понравилась статья? Давайте внедрим это в вашем бизнесе
            </h2>
            <p className="text-secondary-dark mb-8" style={{ fontSize: '16px' }}>
              Бесплатная 30-минутная консультация — обсудим вашу задачу
            </p>
            <a
              href="https://t.me/moi_imperator"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-block font-mono-accent text-white px-8 py-4 hover:bg-[#1a1acc] transition-colors"
              style={{ fontSize: '12px', backgroundColor: '#2C2CFF' }}
            >
              НАПИСАТЬ В ТЕЛЕГРАМ
            </a>
          </div>
        </div>
      </section>

      <FooterSection />
    </div>
  )
}
