import { Link } from 'react-router'
import { trpc } from '@/providers/trpc'
import Navigation from '@/components/Navigation'
import FooterSection from '@/sections/FooterSection'

export default function Blog() {
  const { data: posts, isLoading } = trpc.blog.list.useQuery()

  return (
    <div className="relative">
      <Navigation />

      {/* Blog Hero */}
      <section
        className="relative w-full"
        style={{ backgroundColor: '#141414', paddingTop: '120px', paddingBottom: '80px' }}
      >
        <div className="grid-container">
          <div className="col-span-12">
            <h1
              className="text-white font-medium"
              style={{
                fontSize: 'clamp(36px, 5vw, 60px)',
                lineHeight: '1.1',
                letterSpacing: '-0.03em',
              }}
            >
              БЛОГ
            </h1>
            <p
              className="text-secondary-dark mt-4"
              style={{ fontSize: '14px', maxWidth: '600px' }}
            >
              SEO-статьи про внедрение ИИ, автоматизацию бизнес-процессов, CRM-системы и
              цифровую трансформацию. Реальные кейсы и практические гайды.
            </p>
          </div>
        </div>
      </section>

      {/* Posts Grid */}
      <section className="section-light" style={{ paddingTop: '80px' }}>
        <div className="grid-container">
          {isLoading ? (
            <div className="col-span-12 text-center py-16">
              <p className="text-secondary-light">Загрузка статей...</p>
            </div>
          ) : (
            posts?.map((post) => (
              <div key={post.id} className="col-span-4 mb-8">
                <Link
                  to={`/blog/${post.slug}`}
                  className="block group h-full"
                  style={{ border: '1px solid #EAEAEA' }}
                >
                  {/* Cover */}
                  <div
                    className="relative overflow-hidden"
                    style={{ aspectRatio: '16/9' }}
                  >
                    {post.coverImage?.endsWith('.mp4') ? (
                      <video
                        autoPlay
                        loop
                        muted
                        playsInline
                        className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                      >
                        <source src={post.coverImage} type="video/mp4" />
                      </video>
                    ) : (
                      <img
                        src={post.coverImage || '/images/img_services_chatbot.jpg'}
                        alt={post.title}
                        className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                      />
                    )}
                  </div>

                  {/* Content */}
                  <div className="p-6">
                    <div
                      className="font-mono-accent text-secondary-light mb-2"
                      style={{ fontSize: '11px' }}
                    >
                      {post.createdAt
                        ? new Date(post.createdAt).toLocaleDateString('ru-RU')
                        : ''}
                    </div>
                    <h3
                      className="text-black font-medium mb-3 group-hover:text-[#2C2CFF] transition-colors"
                      style={{
                        fontSize: '20px',
                        lineHeight: '26px',
                        letterSpacing: '-0.02em',
                      }}
                    >
                      {post.title}
                    </h3>
                    <p
                      className="text-secondary-light"
                      style={{ fontSize: '14px', lineHeight: '21px' }}
                    >
                      {post.excerpt}
                    </p>
                    <span
                      className="font-mono-accent text-black mt-4 inline-block border-b border-black pb-1 group-hover:text-[#2C2CFF] group-hover:border-[#2C2CFF] transition-colors"
                      style={{ fontSize: '11px' }}
                    >
                      ЧИТАТЬ СТАТЬЮ
                    </span>
                  </div>
                </Link>
              </div>
            ))
          )}
        </div>
      </section>

      {/* CTA Section */}
      <section
        style={{ backgroundColor: '#141414', padding: '80px 0', textAlign: 'center' }}
      >
        <div className="grid-container">
          <div className="col-span-12">
            <h2
              className="text-white font-medium mb-4"
              style={{ fontSize: '32px', lineHeight: '1.2' }}
            >
              Хотите подобные результаты?
            </h2>
            <p className="text-secondary-dark mb-8" style={{ fontSize: '16px' }}>
              Обсудим вашу задачу на бесплатном 30-минутном созвоне
            </p>
            <a
              href="https://t.me/moi_imperator"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-block font-mono-accent text-white px-8 py-4 hover:bg-[#1a1acc] transition-colors"
              style={{ fontSize: '12px', backgroundColor: '#2C2CFF' }}
            >
              ЗАПИСАТЬСЯ НА КОНСУЛЬТАЦИЮ
            </a>
          </div>
        </div>
      </section>

      <FooterSection />
    </div>
  )
}
