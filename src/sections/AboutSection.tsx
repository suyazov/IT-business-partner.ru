import { useRef, useEffect } from 'react'

export default function AboutSection() {
  const sectionRef = useRef<HTMLElement>(null)
  const textRef = useRef<HTMLDivElement>(null)
  const videoRef = useRef<HTMLDivElement>(null)
  const linesRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            if (textRef.current) {
              textRef.current.style.opacity = '1'
              textRef.current.style.transform = 'translateY(0)'
            }
            if (videoRef.current) {
              videoRef.current.style.opacity = '1'
              videoRef.current.style.transform = 'translateY(0)'
            }
            if (linesRef.current) {
              const lines = linesRef.current.querySelectorAll('.grid-line-v')
              lines.forEach((line, i) => {
                setTimeout(() => {
                  ;(line as HTMLElement).style.transform = 'scaleY(1)'
                }, i * 100)
              })
            }
            observer.unobserve(entry.target)
          }
        })
      },
      { threshold: 0.2 }
    )

    if (sectionRef.current) {
      observer.observe(sectionRef.current)
    }

    return () => observer.disconnect()
  }, [])

  return (
    <section
      id="about"
      ref={sectionRef}
      className="section-light relative overflow-hidden"
    >
      {/* Grid Lines */}
      <div ref={linesRef} className="absolute inset-0 pointer-events-none">
        {[...Array(11)].map((_, i) => (
          <div
            key={i}
            className="grid-line-v"
            style={{
              left: `${((i + 1) / 12) * 100}%`,
              transform: 'scaleY(0)',
              transition: 'transform 1.2s cubic-bezier(0.16, 1, 0.3, 1)',
              backgroundColor: '#EAEAEA'
            }}
          />
        ))}
      </div>

      <div className="grid-container relative" style={{ zIndex: 1 }}>
        {/* Section Header */}
        <div className="col-span-12 mb-16">
          <h2
            className="font-mono-accent text-black"
            style={{ fontSize: '12px' }}
          >
            (01) О СЕБЕ
          </h2>
        </div>

        {/* Text Content */}
        <div
          ref={textRef}
          className="col-span-8"
          style={{
            opacity: 0,
            transform: 'translateY(20px)',
            transition: 'all 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.2s'
          }}
        >
          <p
            className="text-black"
            style={{
              fontSize: '16px',
              lineHeight: '24px',
              marginBottom: '24px'
            }}
          >
            Я — Артём Суязов, 37 лет, Москва. 16 лет в IT: от системной интеграции до AI-first
            подхода. После сокращения в крупном промышленном холдинге (1800+ сотрудников)
            запустил собственную практику IT-бизнес-партнёрства.
          </p>
          <p
            className="text-secondary-light"
            style={{
              fontSize: '16px',
              lineHeight: '24px',
              marginBottom: '24px'
            }}
          >
            Работаю в парадигме AI-first: использую ИИ для анализа, проектирования процессов,
            ускорения решений. Практикую вайб-кодинг — создаю сайты, скрипты и продукты,
            говоря на одном языке с бизнесом и разработчиками.
          </p>
          <p
            className="text-secondary-light"
            style={{
              fontSize: '16px',
              lineHeight: '24px'
            }}
          >
            Сейчас работаю с двумя компаниями как внешний IT-бизнес-партнёр: формирую архитектуру
            процессов, управляю цифровыми инициативами, создаю решения, которые напрямую влияют
            на рост бизнеса.
          </p>

          {/* Stats */}
          <div className="flex gap-16 mt-12 flex-wrap">
            <div>
              <div
                className="text-black font-medium"
                style={{
                  fontSize: '48px',
                  lineHeight: '48px',
                  letterSpacing: '-0.02em'
                }}
              >
                16+
              </div>
              <div
                className="text-secondary-light mt-2"
                style={{ fontSize: '14px' }}
              >
                Лет в IT
              </div>
            </div>
            <div>
              <div
                className="text-black font-medium"
                style={{
                  fontSize: '48px',
                  lineHeight: '48px',
                  letterSpacing: '-0.02em'
                }}
              >
                2
              </div>
              <div
                className="text-secondary-light mt-2"
                style={{ fontSize: '14px' }}
              >
                Активных проекта
              </div>
            </div>
            <div>
              <div
                className="text-black font-medium"
                style={{
                  fontSize: '48px',
                  lineHeight: '48px',
                  letterSpacing: '-0.02em'
                }}
              >
                10+
              </div>
              <div
                className="text-secondary-light mt-2"
                style={{ fontSize: '14px' }}
              >
                Млн ₽ сэкономлено
              </div>
            </div>
            <div>
              <div
                className="text-black font-medium"
                style={{
                  fontSize: '48px',
                  lineHeight: '48px',
                  letterSpacing: '-0.02em'
                }}
              >
                AI
              </div>
              <div
                className="text-secondary-light mt-2"
                style={{ fontSize: '14px' }}
              >
                First подход
              </div>
            </div>
          </div>
        </div>

        {/* Video */}
        <div
          ref={videoRef}
          className="col-span-4"
          style={{
            opacity: 0,
            transform: 'translateY(20px)',
            transition: 'all 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.4s'
          }}
        >
          <video
            autoPlay
            loop
            muted
            playsInline
            className="w-full h-auto"
            style={{ aspectRatio: '1/1', objectFit: 'cover' }}
          >
            <source src="/videos/video_about_morph.mp4" type="video/mp4" />
          </video>
        </div>
      </div>
    </section>
  )
}
