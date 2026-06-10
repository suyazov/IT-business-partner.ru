import { useState, useRef, useEffect } from 'react'

interface FAQItem {
  question: string
  answer: string
}

const faqItems: FAQItem[] = [
  {
    question: 'Чем отличается IT-бизнес-партнёр от обычного разработчика или агентства?',
    answer:
      'Я работаю на стыке бизнеса и технологий. Не просто пишу код или настраиваю CRM, а формирую стратегию цифровизации, управляю портфелем проектов и отвечаю за бизнес-результат. 16 лет опыта в IT + AI-first подход + вайб-кодинг = скорость и качество решений.'
  },
  {
    question: 'Как происходит работа удалённо?',
    answer:
      'Я работаю с клиентами по всей России. Встречи — в Zoom или Telegram, документация в Notion, задачи в Trello/Asana. Для крупных проектов в Москве возможны личные встречи. Главное — регулярная коммуникация и прозрачные метрики.'
  },
  {
    question: 'Какие технологии вы используете?',
    answer:
      'PHP, JavaScript, WordPress, OpenAI API, Telegram Bot API, 1С:ERP, Bitrix24, любые внешние API. Выбираю стек под задачу. AI-first — не тренд, а рабочий инструмент, который усиливает стратегию и ускоряет реализацию.'
  },
  {
    question: 'Что такое вайб-кодинг и почему это важно?',
    answer:
      'Вайб-кодинг — подход, при котором я сам создаю сайты, скрипты и продукты, глубоко понимая архитектуру. Это позволяет говорить на одном языке с разработчиками, быстро проверять гипотезы и не зависеть от подрядчиков. Результат — скорость и контроль качества.'
  },
  {
    question: 'Какой формат сотрудничества вы предлагаете?',
    answer:
      'Три формата: (1) бесплатный 30-минутный звонок для диагностики, (2) постоянное партнёрство от 80 000 ₽/мес — я как внешний IT-директор, (3) проект под ключ от 150 000 ₽ — конкретный проект с чёткими сроками и результатом.'
  },
  {
    question: 'Как начать работу?',
    answer:
      'Напишите мне в Telegram @moi_imperator — обсудим задачу. Если нужно — проведу бесплатный 30-минутный созвон, чтобы понять, подходят ли мои услуги под ваш запрос.'
  }
]

function AccordionItem({
  item,
  isOpen,
  onToggle
}: {
  item: FAQItem
  isOpen: boolean
  onToggle: () => void
}) {
  return (
    <div style={{ borderBottom: '1px solid #EAEAEA' }}>
      <button
        onClick={onToggle}
        className="w-full flex items-center justify-between py-6 text-left group"
      >
        <span
          className="text-black font-medium pr-8"
          style={{ fontSize: '18px', lineHeight: '24px' }}
        >
          {item.question}
        </span>
        <span
          className="text-black flex-shrink-0 transition-transform duration-300"
          style={{
            transform: isOpen ? 'rotate(45deg)' : 'rotate(0deg)',
            fontSize: '24px'
          }}
        >
          +
        </span>
      </button>
      <div
        className="overflow-hidden transition-all duration-500"
        style={{
          maxHeight: isOpen ? '300px' : '0',
          opacity: isOpen ? 1 : 0
        }}
      >
        <p
          className="text-secondary-light pb-6"
          style={{ fontSize: '15px', lineHeight: '24px' }}
        >
          {item.answer}
        </p>
      </div>
    </div>
  )
}

export default function FAQSection() {
  const [openIndex, setOpenIndex] = useState<number | null>(null)
  const sectionRef = useRef<HTMLElement>(null)
  const contentRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            if (contentRef.current) {
              contentRef.current.style.opacity = '1'
              contentRef.current.style.transform = 'translateY(0)'
            }
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
      id="faq"
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
            (06) FAQ
          </h2>
        </div>

        {/* FAQ Content */}
        <div
          ref={contentRef}
          className="col-span-8 col-start-3"
          style={{
            opacity: 0,
            transform: 'translateY(20px)',
            transition: 'all 0.8s cubic-bezier(0.16, 1, 0.3, 1)'
          }}
        >
          {faqItems.map((item, index) => (
            <AccordionItem
              key={index}
              item={item}
              isOpen={openIndex === index}
              onToggle={() =>
                setOpenIndex(openIndex === index ? null : index)
              }
            />
          ))}
        </div>
      </div>
    </section>
  )
}
