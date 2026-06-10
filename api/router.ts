import { createRouter, publicQuery } from "./middleware";
import { blogRouter } from "./routers/blog";

export const appRouter = createRouter({
  ping: publicQuery.query(() => ({ ok: true, ts: Date.now() })),
  blog: blogRouter,
});

export type AppRouter = typeof appRouter;
