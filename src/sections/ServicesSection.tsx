import { useRef, useEffect } from 'react'

const services = [
  {
    id: '02.01',
    title: 'AI-КОНТЕНТ-МАРКЕТИНГ',
    description:
      'Генерация SEO-статей, постов для соцсетей, описаний товаров. AI-first подход к контент-производству: от идеи до публикации в 3 раза быстрее. Реальный кейс: 32 статьи за 4 месяца для Технодарка.',
    media: '/videos/video_services_content.mp4',
    mediaType: 'video'
  },
  {
    id: '02.02',
    title: 'АВТОМАТИЗАЦИЯ ВОРОНКИ ПРОДАЖ',
    description:
      'CRM-системы, сквозная аналитика, интеграция 1С:ERP, Bitrix24, управление лидами от первого контакта до сделки. Прозрачность данных для принятия решений.',
    media: '/videos/video_services_scripts.mp4',
    mediaType: 'video'
  },
  {
    id: '02.03',
    title: 'LMS-ПЛАТФОРМЫ И ОБРАЗОВАНИЕ',
    description:
      'Разработка и развёртывание LMS-платформ, автоматизация образовательных процессов, интеграция систем маркетинга и аналитики. Масштабирование образовательного продукта.',
    media: '/videos/video_services_employees.mp4',
    mediaType: 'video'
  },
  {
    id: '02.04',
    title: 'ЦИФРОВАЯ ТРАНСФОРМАЦИЯ',
    description:
      'Полный цикл: от аудита процессов до внедрения. WMS-системы, управленческий учёт (1С:БитФинанс), финансовое планирование, цифровизация производства. Экономия 10+ млн ₽ за счёт отказа от внешних интеграторов.',
    media: '/images/img_services_chatbot.jpg',
    mediaType: 'image'
  }
]

export default function ServicesSection() {
  const sectionRef = useRef<HTMLElement>(null)
  const cardsRef = useRef<(HTMLDivElement | null)[]>([])

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            cardsRef.current.forEach((card, i) => {
              if (card) {
                setTimeout(() => {
                  card.style.opacity = '1'
                  card.style.transform = 'translateX(0)'
                }, i * 200)
              }
            })
            observer.unobserve(entry.target)
          }
        })
      },
      { threshold: 0.15 }
    )

    if (sectionRef.current) {
      observer.observe(sectionRef.current)
    }

    return () => observer.disconnect()
  }, [])

  return (
    <section
      id="services"
      ref={sectionRef}
      className="section-light relative overflow-hidden"
    >
      <div className="grid-container">
        {/* Section Header */}
        <div className="col-span-12 mb-16">
          <h2
            className="font-mono-accent text-black"
            style={{ fontSize: '12px' }}
          >
            (02) НЕЙРОСЕТЕВЫЕ РЕШЕНИЯ
          </h2>
        </div>

        {/* Service Cards */}
        {services.map((service, index) => (
          <div
            key={service.id}
            ref={(el) => { cardsRef.current[index] = el }}
            className="col-span-12 mb-8"
            style={{
              opacity: 0,
              transform: 'translateX(50px)',
              transition: 'all 0.8s cubic-bezier(0.16, 1, 0.3, 1)'
            }}
          >
            <div
              className="grid grid-cols-12 gap-6 items-center py-8"
              style={{
                borderTop: '1px solid #EAEAEA'
              }}
            >
              {/* Service Number & Title */}
              <div className="col-span-4">
                <span
                  className="font-mono-accent text-secondary-light block mb-2"
                  style={{ fontSize: '12px' }}
                >
                  ({service.id})
                </span>
                <h3
                  className="text-black font-medium"
                  style={{
                    fontSize: 'clamp(24px, 3vw, 36px)',
                    lineHeight: '1.1',
                    letterSpacing: '-0.02em'
                  }}
                >
                  {service.title}
                </h3>
              </div>

              {/* Media */}
              <div className="col-span-5">
                {service.mediaType === 'video' ? (
                  <video
                    autoPlay
                    loop
                    muted
                    playsInline
                    className="w-full h-auto rounded"
                    style={{ aspectRatio: '3/2', objectFit: 'cover' }}
                  >
                    <source src={service.media} type="video/mp4" />
                  </video>
                ) : (
                  <img
                    src={service.media}
                    alt={service.title}
                    className="w-full h-auto rounded"
                    style={{ aspectRatio: '3/2', objectFit: 'cover' }}
                  />
                )}
              </div>

              {/* Description & CTA */}
              <div className="col-span-3">
                <p
                  className="text-secondary-light mb-6"
                  style={{ fontSize: '14px', lineHeight: '21px' }}
                >
                  {service.description}
                </p>
                <a
                  href="https://t.me/moi_imperator"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="font-mono-accent text-black border-b border-black pb-1 hover:text-[#2C2CFF] hover:border-[#2C2CFF] transition-colors"
                >
                  УЗНАТЬ БОЛЬШЕ
                </a>
              </div>
            </div>
          </div>
        ))}
      </div>
    </section>
  )
}
